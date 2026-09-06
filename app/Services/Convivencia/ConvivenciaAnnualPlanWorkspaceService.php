<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaPlan;
use App\Models\Convivencia\ConvivenciaPlanAction;
use App\Models\Convivencia\ConvivenciaPlanActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ConvivenciaAnnualPlanWorkspaceService
{
    public function __construct(private readonly ConvivenciaAccessService $accessService) {}

    public function loadByYear(int $year, User $user): ?ConvivenciaPlan
    {
        return $this->baseQuery($user)->where('calendar_year', $year)->first();
    }

    public function loadPlan(ConvivenciaPlan $plan, User $user): ConvivenciaPlan
    {
        return $this->baseQuery($user)->findOrFail($plan->id);
    }

    public function loadAction(ConvivenciaPlanAction $action, User $user): ConvivenciaPlanAction
    {
        return ConvivenciaPlanAction::query()
            ->with([
                'plan:id,calendar_year,name,status,is_sensitive,responsible_user_id,created_by',
                'dimension:id,name',
                'responsibleUser:id,name',
                'responsibleStaff:id,full_name',
                'responsibleDepartment:id,name',
                'activities' => fn ($query) => $query->with([
                    'activityType:id,code,name,color,metadata',
                    'attachments' => fn ($attachmentQuery) => $this->accessService
                        ->applyAttachmentVisibility($attachmentQuery, $user)
                        ->with('uploadedBy:id,name'),
                ]),
            ])
            ->withCount([
                'activities',
                'activities as completed_activities_count' => fn ($query) => $query->where('status', 'realizada'),
                'attachments' => fn ($query) => $this->accessService->applyAttachmentVisibility($query, $user),
            ])
            ->withSum('activities as allocated_contribution', 'contribution_percent')
            ->findOrFail($action->id);
    }

    public function exportPlan(ConvivenciaPlan $plan, User $user): array
    {
        $plan = $this->loadPlan($plan, $user);
        $plan->load([
            'actions.activities.activityType:id,code,name,color,metadata',
            'actions.activities.attachments' => fn ($query) => $this->accessService
                ->applyAttachmentVisibility($query, $user)
                ->with('uploadedBy:id,name'),
        ]);
        $payload = $this->serializePlan($plan);
        $payload['actions'] = $plan->actions
            ->map(fn (ConvivenciaPlanAction $action) => $this->serializeActionDetail($action))
            ->values();

        return $payload;
    }

    public function serializePlan(ConvivenciaPlan $plan): array
    {
        $actions = $plan->actions;

        return [
            ...$plan->toArray(),
            'actions' => $actions->map(fn (ConvivenciaPlanAction $action) => $this->serializeAction($action))->values(),
            'stats' => [
                'actions' => $actions->count(),
                'completed_actions' => $actions->where('status', 'completada')->count(),
                'in_progress_actions' => $actions->where('status', 'en_ejecucion')->count(),
                'overdue_actions' => $actions->filter(fn ($action) => $action->ends_on
                    && $action->ends_on->isBefore(today())
                    && ! in_array($action->status, ['completada', 'cancelada'], true))->count(),
                'activities' => $actions->sum('activities_count'),
                'completed_activities' => $actions->sum('completed_activities_count'),
                'evidences' => $actions->sum(fn (ConvivenciaPlanAction $action) => (int) (($action->attachments_count ?? 0) + $action->activities->sum('attachments_count'))),
                'progress' => (float) $plan->advance_percentage,
                'action_weight_allocated' => round((float) $actions->sum('weight_percent'), 2),
                'progress_method' => $actions->sum('weight_percent') > 0 ? 'weighted' : 'equal_average',
                'multi_year_actions' => $actions->filter(fn (ConvivenciaPlanAction $action) => $this->isMultiYear($action))->count(),
            ],
        ];
    }

    public function serializeAction(ConvivenciaPlanAction $action): array
    {
        $payload = $action->toArray();
        unset($payload['activities']);
        $payload['activities_count'] = (int) ($action->activities_count ?? 0);
        $payload['completed_activities_count'] = (int) ($action->completed_activities_count ?? 0);
        $payload['evidences_count'] = (int) (($action->attachments_count ?? 0) + $action->activities->sum('attachments_count'));
        $payload['allocated_contribution'] = (int) ($action->allocated_contribution ?? 0);
        $payload['is_multi_year'] = $this->isMultiYear($action);
        $payload['weighted_progress'] = round(((float) $action->weight_percent * (float) $action->advance_percentage) / 100, 2);

        return $payload;
    }

    public function serializeActionDetail(ConvivenciaPlanAction $action): array
    {
        return [
            ...$this->serializeAction($action),
            'activities' => $action->activities->map(fn (ConvivenciaPlanActivity $activity) => [
                ...$activity->toArray(),
                'earned_progress' => $activity->status === 'cancelada' ? 0 : (int) round($activity->contribution_percent * $activity->completion_percent / 100),
            ])->values(),
        ];
    }

    private function baseQuery(User $user): Builder
    {
        return $this->accessService->applyPlanVisibility(
            ConvivenciaPlan::query()
                ->with([
                    'academicYear:id,name,year',
                    'previousPlan:id,calendar_year,name,version_number',
                    'responsibleUser:id,name,email',
                    'responsibleStaff:id,full_name',
                    'approvedBy:id,name',
                    'actions' => fn ($query) => $query
                        ->with([
                            'dimension:id,name',
                            'responsibleUser:id,name',
                            'responsibleStaff:id,full_name',
                            'responsibleDepartment:id,name',
                            'activities' => fn ($activityQuery) => $activityQuery
                                ->select(['id', 'plan_action_id'])
                                ->withCount([
                                    'attachments' => fn ($attachmentQuery) => $this->accessService
                                        ->applyAttachmentVisibility($attachmentQuery, $user),
                                ]),
                        ])
                        ->withCount([
                            'activities',
                            'activities as completed_activities_count' => fn ($activityQuery) => $activityQuery->where('status', 'realizada'),
                            'attachments' => fn ($attachmentQuery) => $this->accessService
                                ->applyAttachmentVisibility($attachmentQuery, $user),
                        ])
                        ->withSum('activities as allocated_contribution', 'contribution_percent')
                        ->orderBy('sort_order')
                        ->orderBy('id'),
                    'versions.createdBy:id,name',
                    'attachments' => fn ($query) => $this->accessService
                        ->applyAttachmentVisibility($query, $user)
                        ->with('uploadedBy:id,name'),
                ]),
            $user,
        );
    }

    private function isMultiYear(ConvivenciaPlanAction $action): bool
    {
        return $action->starts_on
            && $action->ends_on
            && $action->starts_on->year !== $action->ends_on->year;
    }
}
