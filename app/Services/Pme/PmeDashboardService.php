<?php

namespace App\Services\Pme;

use App\Models\Pme\PmeAction;
use App\Models\Pme\PmeEvidence;
use App\Models\Pme\PmeIndicator;
use App\Models\Pme\PmePlan;
use App\Models\Pme\PmeSepIncome;
use App\Models\Pme\PmeStudentSepClassification;
use App\Models\Pme\PmeStrategy;
use App\Models\Pme\PmeObjective;
use Illuminate\Support\Collection;

class PmeDashboardService
{
    public function __construct(
        private readonly PmeAlertService $alertService,
    ) {
    }

    public function build(): array
    {
        $plan = PmePlan::query()
            ->with(['cycles' => fn ($query) => $query->orderBy('sort_order')])
            ->where('is_active', true)
            ->first();

        if (!$plan) {
            return [
                'metrics' => [],
                'charts' => [],
                'alerts' => [],
                'active_plan' => null,
            ];
        }

        $objectives = PmeObjective::query()->where('pme_plan_id', $plan->id)->get();
        $strategies = PmeStrategy::query()->whereHas('objective', fn ($query) => $query->where('pme_plan_id', $plan->id))->get();
        $indicators = PmeIndicator::query()
            ->with(['objective.dimension:id,name'])
            ->whereHas('objective', fn ($query) => $query->where('pme_plan_id', $plan->id))
            ->get();
        $actions = PmeAction::query()
            ->with(['dimension:id,name', 'responsibleUser:id,name'])
            ->withCount('evidences')
            ->where('pme_plan_id', $plan->id)
            ->get();
        $evidences = PmeEvidence::query()
            ->whereHas('action', fn ($query) => $query->where('pme_plan_id', $plan->id))
            ->get();
        $incomes = PmeSepIncome::query()->where('pme_plan_id', $plan->id)->get();
        $students = PmeStudentSepClassification::query()
            ->with('courseSection:id,display_name')
            ->where('academic_year_id', $plan->academic_year_id)
            ->get();
        $alerts = $this->alertService->build($plan);

        $budgetEstimated = (float) $incomes->sum('estimated_amount');
        $budgetCommitted = (float) $actions->sum('committed_budget');
        $budgetExecuted = (float) $actions->sum('executed_budget');

        return [
            'active_plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'school_year' => $plan->school_year,
                'state' => $plan->state,
                'cycle' => $plan->cycle_name,
            ],
            'metrics' => [
                'pme_active' => $plan->name,
                'cycle_active' => $plan->cycles->firstWhere('is_current', true)?->name ?? $plan->cycle_name,
                'objectives_total' => $objectives->count(),
                'strategies_total' => $strategies->count(),
                'indicators_total' => $indicators->count(),
                'actions_total' => $actions->count(),
                'actions_planned' => $actions->where('state', 'planificada')->count(),
                'actions_execution' => $actions->whereIn('state', ['aprobada', 'en_ejecucion', 'en_monitoreo'])->count(),
                'actions_finished' => $actions->whereIn('state', ['finalizada', 'cerrada'])->count(),
                'actions_late' => $actions->filter(fn (PmeAction $action) => $action->state === 'atrasada' || ($action->end_date && $action->end_date->isPast() && !in_array($action->state, ['finalizada', 'cerrada', 'anulada'], true)))->count(),
                'actions_without_evidence' => $actions->filter(fn (PmeAction $action) => $action->evidences_count === 0)->count(),
                'evidences_pending' => $evidences->whereIn('review_status', ['cargada', 'en_revision'])->count(),
                'evidences_approved' => $evidences->where('review_status', 'aprobada')->count(),
                'evidences_rejected' => $evidences->where('review_status', 'rechazada')->count(),
                'sep_incomes_registered' => $incomes->count(),
                'sep_budget_estimated' => $budgetEstimated,
                'budget_committed' => $budgetCommitted,
                'budget_executed' => $budgetExecuted,
                'budget_available' => $budgetEstimated - $budgetExecuted,
                'priority_students' => $students->where('classification', 'prioritaria')->count(),
                'preferential_students' => $students->where('classification', 'preferente')->count(),
                'global_progress' => round((float) $actions->avg('progress_percentage'), 2),
                'strategic_goal_compliance' => round((float) $indicators->avg('compliance_percentage'), 2),
                'critical_indicators' => $indicators->where('state', 'critico')->count(),
                'pending_monitorings' => $alerts->firstWhere('type', 'monitoreos_pendientes')['count'] ?? 0,
            ],
            'charts' => [
                'progress_by_dimension' => $this->groupAverage($actions, fn (PmeAction $action) => $action->dimension?->name ?? 'Sin dimensión', 'progress_percentage'),
                'actions_by_month' => $this->groupCountByMonth($actions->pluck('start_date')->filter()->values()),
                'actions_by_state' => $this->groupCount($actions, fn (PmeAction $action) => $action->state),
                'actions_by_responsible' => $this->groupCount($actions, fn (PmeAction $action) => $action->responsibleUser?->name ?? 'Sin responsable'),
                'actions_by_dimension' => $this->groupCount($actions, fn (PmeAction $action) => $action->dimension?->name ?? 'Sin dimensión'),
                'budget_by_action' => $actions->take(10)->map(fn (PmeAction $action) => [
                    'label' => $action->name,
                    'planned' => (float) $action->planned_budget,
                    'executed' => (float) $action->executed_budget,
                ])->values(),
                'budget_by_dimension' => $actions->groupBy(fn (PmeAction $action) => $action->dimension?->name ?? 'Sin dimensión')->map(fn (Collection $group, string $label) => [
                    'label' => $label,
                    'planned' => round((float) $group->sum('planned_budget'), 2),
                    'executed' => round((float) $group->sum('executed_budget'), 2),
                ])->values(),
                'indicators_compliance' => $this->groupAverage($indicators, fn (PmeIndicator $indicator) => $indicator->objective?->dimension?->name ?? 'Sin dimensión', 'compliance_percentage'),
                'evidences_by_month' => $this->groupCountByMonth($evidences->pluck('uploaded_at')->filter()->values()),
                'students_by_course' => $students->groupBy(fn ($student) => $student->courseSection?->display_name ?? 'Sin curso')->map(fn (Collection $group, string $label) => [
                    'label' => $label,
                    'prioritarias' => $group->where('classification', 'prioritaria')->count(),
                    'preferentes' => $group->where('classification', 'preferente')->count(),
                ])->values(),
                'incomes_by_month' => $incomes->groupBy('month')->map(fn (Collection $group, $month) => [
                    'label' => (int) $month,
                    'estimated' => round((float) $group->sum('estimated_amount'), 2),
                    'received' => round((float) $group->sum('received_amount'), 2),
                ])->sortBy('label')->values(),
                'planned_vs_executed' => [
                    ['label' => 'Planificado', 'total' => round((float) $actions->sum('planned_budget'), 2)],
                    ['label' => 'Ejecutado', 'total' => round((float) $actions->sum('executed_budget'), 2)],
                ],
                'progress_by_area' => $this->groupAverage($actions, fn (PmeAction $action) => $action->responsible_area ?: 'Sin área', 'progress_percentage'),
            ],
            'alerts' => $alerts,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $items
     */
    private function groupCount(Collection $items, callable $labelResolver): Collection
    {
        return $items->groupBy($labelResolver)->map(fn (Collection $group, string $label) => [
            'label' => $label,
            'total' => $group->count(),
        ])->values();
    }

    /**
     * @param  Collection<int, mixed>  $items
     */
    private function groupAverage(Collection $items, callable $labelResolver, string $field): Collection
    {
        return $items->groupBy($labelResolver)->map(fn (Collection $group, string $label) => [
            'label' => $label,
            'total' => round((float) $group->avg($field), 2),
        ])->values();
    }

    /**
     * @param  Collection<int, mixed>  $dates
     */
    private function groupCountByMonth(Collection $dates): Collection
    {
        return $dates
            ->map(fn ($date) => optional($date)->format('Y-m'))
            ->filter()
            ->groupBy(fn (string $month) => $month)
            ->map(fn (Collection $group, string $label) => ['label' => $label, 'total' => $group->count()])
            ->sortBy('label')
            ->values();
    }
}
