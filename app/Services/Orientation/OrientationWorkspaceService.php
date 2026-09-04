<?php

namespace App\Services\Orientation;

use App\Models\Orientation\OrientationAction;
use App\Models\Orientation\OrientationEvidence;
use App\Models\Orientation\OrientationPlan;
use App\Models\Orientation\OrientationRelatedPlan;
use App\Models\User;
use Illuminate\Support\Collection;

class OrientationWorkspaceService
{
    public function loadPlan(int $year): ?OrientationPlan
    {
        return OrientationPlan::query()
            ->where('year', $year)
            ->with([
                'owner:id,name',
                'relatedPlans' => fn ($query) => $query->withCount('actions'),
                'actions' => fn ($query) => $query
                    ->with([
                        'responsibleUsers:id,name',
                        'relatedPlans:id,orientation_plan_id,name,category,status',
                    ])
                    ->withCount([
                        'activities',
                        'evidences',
                        'activities as completed_activities_count' => fn ($activityQuery) => $activityQuery->where('status', 'completed'),
                    ])
                    ->withSum('activities as activity_contribution_allocated', 'contribution_percent'),
            ])
            ->first();
    }

    public function loadAction(OrientationAction $action): OrientationAction
    {
        return $action->load([
            'plan:id,year,title',
            'responsibleUsers:id,name',
            'relatedPlans:id,orientation_plan_id,name,category,status,reference_url',
            'activities' => fn ($query) => $query->withCount('evidences'),
            'evidences.activity:id,title',
            'evidences.uploadedBy:id,name',
        ]);
    }

    public function serializePlan(OrientationPlan $plan): array
    {
        $actions = $plan->actions;
        $activityCount = $actions->sum('activities_count');
        $completedActivityCount = $actions->sum('completed_activities_count');

        return [
            'id' => $plan->id,
            'year' => $plan->year,
            'title' => $plan->title,
            'general_objective' => $plan->general_objective,
            'description' => $plan->description,
            'status' => $plan->status,
            'owner_user_id' => $plan->owner_user_id,
            'owner' => $plan->owner,
            'actions' => $actions->map(fn (OrientationAction $action) => $this->serializeActionSummary($action))->values(),
            'related_plans' => $plan->relatedPlans->map(fn (OrientationRelatedPlan $relatedPlan) => $this->serializeRelatedPlan($relatedPlan))->values(),
            'stats' => [
                'actions' => $actions->count(),
                'completed_actions' => $actions->where('status', 'completed')->count(),
                'in_progress_actions' => $actions->where('status', 'in_progress')->count(),
                'overdue_actions' => $actions->filter(fn (OrientationAction $action) => $action->end_date
                    && $action->end_date->isBefore(today())
                    && ! in_array($action->status, ['completed', 'cancelled'], true))->count(),
                'activities' => $activityCount,
                'completed_activities' => $completedActivityCount,
                'evidences' => $actions->sum('evidences_count'),
                'progress' => $actions->count() ? (int) round($actions->avg('progress')) : 0,
            ],
            'created_at' => optional($plan->created_at)->toDateTimeString(),
            'updated_at' => optional($plan->updated_at)->toDateTimeString(),
        ];
    }

    public function serializeActionSummary(OrientationAction $action): array
    {
        return [
            'id' => $action->id,
            'orientation_plan_id' => $action->orientation_plan_id,
            'title' => $action->title,
            'objective' => $action->objective,
            'description' => $action->description,
            'target_levels' => $action->target_levels,
            'planned_verification_means' => $action->planned_verification_means,
            'material_resources' => $action->material_resources,
            'responsible_summary' => $action->responsible_summary,
            'start_date' => optional($action->start_date)->toDateString(),
            'end_date' => optional($action->end_date)->toDateString(),
            'status' => $action->status,
            'progress' => $action->progress,
            'sort_order' => $action->sort_order,
            'responsible_users' => $action->responsibleUsers,
            'responsible_user_ids' => $action->responsibleUsers->pluck('id')->values(),
            'related_plans' => $action->relatedPlans,
            'related_plan_ids' => $action->relatedPlans->pluck('id')->values(),
            'activities_count' => (int) ($action->activities_count ?? 0),
            'completed_activities_count' => (int) ($action->completed_activities_count ?? 0),
            'evidences_count' => (int) ($action->evidences_count ?? 0),
            'activity_contribution_allocated' => (int) ($action->activity_contribution_allocated ?? 0),
            'activity_contribution_remaining' => max(0, 100 - (int) ($action->activity_contribution_allocated ?? 0)),
        ];
    }

    public function serializeActionDetail(OrientationAction $action): array
    {
        return array_merge($this->serializeActionSummary($action), [
            'plan' => $action->plan,
            'activities' => $action->activities->map(fn ($activity) => [
                'id' => $activity->id,
                'orientation_action_id' => $activity->orientation_action_id,
                'title' => $activity->title,
                'description' => $activity->description,
                'starts_at' => optional($activity->starts_at)->toIso8601String(),
                'ends_at' => optional($activity->ends_at)->toIso8601String(),
                'status' => $activity->status,
                'contribution_percent' => (int) $activity->contribution_percent,
                'completion_percent' => (int) $activity->completion_percent,
                'earned_progress' => (int) round(
                    $activity->status === 'cancelled'
                        ? 0
                        : ($activity->contribution_percent * $activity->completion_percent) / 100
                ),
                'location' => $activity->location,
                'participants' => $activity->participants,
                'attendee_count' => $activity->attendee_count,
                'results' => $activity->results,
                'evidences_count' => (int) ($activity->evidences_count ?? 0),
            ])->values(),
            'evidences' => $action->evidences->map(fn (OrientationEvidence $evidence) => $this->serializeEvidence($evidence))->values(),
            'activity_contribution' => $this->activityContribution($action),
        ]);
    }

    private function activityContribution(OrientationAction $action): array
    {
        $allocated = (int) $action->activities->sum('contribution_percent');
        $earned = (int) round($action->activities->sum(fn ($activity): float => $activity->status === 'cancelled'
            ? 0
            : ($activity->contribution_percent * $activity->completion_percent) / 100));

        return [
            'allocated' => $allocated,
            'earned' => $earned,
            'remaining' => max(0, 100 - $allocated),
        ];
    }

    public function serializeRelatedPlan(OrientationRelatedPlan $relatedPlan): array
    {
        return [
            'id' => $relatedPlan->id,
            'orientation_plan_id' => $relatedPlan->orientation_plan_id,
            'name' => $relatedPlan->name,
            'category' => $relatedPlan->category,
            'description' => $relatedPlan->description,
            'reference_url' => $relatedPlan->reference_url,
            'status' => $relatedPlan->status,
            'actions_count' => (int) ($relatedPlan->actions_count ?? 0),
        ];
    }

    public function serializeEvidence(OrientationEvidence $evidence): array
    {
        return [
            'id' => $evidence->id,
            'orientation_action_id' => $evidence->orientation_action_id,
            'orientation_activity_id' => $evidence->orientation_activity_id,
            'activity' => $evidence->activity,
            'evidence_type' => $evidence->evidence_type,
            'title' => $evidence->title,
            'description' => $evidence->description,
            'occurred_on' => optional($evidence->occurred_on)->toDateString(),
            'original_name' => $evidence->original_name,
            'mime_type' => $evidence->mime_type,
            'size_bytes' => $evidence->size_bytes,
            'external_url' => $evidence->external_url,
            'has_file' => filled($evidence->file_path),
            'download_url' => filled($evidence->file_path) ? "/api/orientation/evidences/{$evidence->id}/download" : null,
            'uploaded_by' => $evidence->uploadedBy,
            'created_at' => optional($evidence->created_at)->toDateTimeString(),
        ];
    }

    public function responsibleUsers(): Collection
    {
        return User::query()
            ->where('active', true)
            ->where(function ($query): void {
                $query->where('user_type', 'staff')->orWhereNotNull('staff_id');
            })
            ->orderBy('name')
            ->limit(750)
            ->get(['id', 'name']);
    }

    public function statusOptions(): array
    {
        return [
            'plans' => [
                ['value' => 'draft', 'label' => 'Borrador'],
                ['value' => 'active', 'label' => 'Vigente'],
                ['value' => 'completed', 'label' => 'Finalizado'],
                ['value' => 'archived', 'label' => 'Archivado'],
            ],
            'actions' => [
                ['value' => 'planned', 'label' => 'Planificada'],
                ['value' => 'in_progress', 'label' => 'En ejecución'],
                ['value' => 'completed', 'label' => 'Completada'],
                ['value' => 'postponed', 'label' => 'Postergada'],
                ['value' => 'cancelled', 'label' => 'Cancelada'],
            ],
            'activities' => [
                ['value' => 'scheduled', 'label' => 'Programada'],
                ['value' => 'in_progress', 'label' => 'En ejecución'],
                ['value' => 'completed', 'label' => 'Realizada'],
                ['value' => 'cancelled', 'label' => 'Cancelada'],
            ],
        ];
    }
}
