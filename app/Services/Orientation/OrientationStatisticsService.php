<?php

namespace App\Services\Orientation;

use App\Models\Orientation\OrientationAction;
use App\Models\Orientation\OrientationActivity;
use App\Models\Orientation\OrientationEvidence;
use App\Models\Orientation\OrientationPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OrientationStatisticsService
{
    private const MONTHS = [
        1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
        5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
    ];

    private const ACTION_STATUS_LABELS = [
        'planned' => 'Planificadas',
        'in_progress' => 'En ejecución',
        'completed' => 'Completadas',
        'postponed' => 'Postergadas',
        'cancelled' => 'Canceladas',
    ];

    private const ACTIVITY_STATUS_LABELS = [
        'scheduled' => 'Programadas',
        'in_progress' => 'En ejecución',
        'completed' => 'Realizadas',
        'cancelled' => 'Canceladas',
    ];

    private const EVIDENCE_TYPE_LABELS = [
        'photograph' => 'Fotografías',
        'attendance' => 'Asistencia',
        'minute' => 'Actas',
        'report' => 'Informes',
        'survey' => 'Encuestas',
        'material' => 'Materiales',
        'other' => 'Otras',
    ];

    public function forPlan(OrientationPlan $plan): array
    {
        $actions = OrientationAction::query()->where('orientation_plan_id', $plan->id);
        $today = today()->toDateString();

        $totals = (clone $actions)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COALESCE(ROUND(AVG(progress)), 0) as average_progress")
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress")
            ->selectRaw("SUM(CASE WHEN status = 'planned' THEN 1 ELSE 0 END) as planned")
            ->selectRaw("SUM(CASE WHEN status = 'postponed' THEN 1 ELSE 0 END) as postponed")
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled")
            ->selectRaw("SUM(CASE WHEN end_date < ? AND status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as overdue", [$today])
            ->selectRaw('SUM(CASE WHEN start_date IS NULL OR end_date IS NULL THEN 1 ELSE 0 END) as unscheduled')
            ->selectRaw("SUM(CASE WHEN responsible_summary IS NOT NULL AND TRIM(responsible_summary) <> '' OR EXISTS (SELECT 1 FROM orientation_action_responsibles oar WHERE oar.orientation_action_id = orientation_actions.id) THEN 1 ELSE 0 END) as with_responsible")
            ->selectRaw("SUM(CASE WHEN planned_verification_means IS NOT NULL AND TRIM(planned_verification_means) <> '' THEN 1 ELSE 0 END) as with_verification")
            ->selectRaw('SUM(CASE WHEN EXISTS (SELECT 1 FROM orientation_evidences oe WHERE oe.orientation_action_id = orientation_actions.id) THEN 1 ELSE 0 END) as with_evidence')
            ->selectRaw('SUM(CASE WHEN EXISTS (SELECT 1 FROM orientation_action_related_plan oarp WHERE oarp.orientation_action_id = orientation_actions.id) THEN 1 ELSE 0 END) as with_related_plan')
            ->selectRaw('SUM(CASE WHEN start_date IS NOT NULL AND end_date IS NOT NULL THEN 1 ELSE 0 END) as with_schedule')
            ->first();

        $activityTotals = OrientationActivity::query()
            ->join('orientation_actions', 'orientation_actions.id', '=', 'orientation_activities.orientation_action_id')
            ->where('orientation_actions.orientation_plan_id', $plan->id)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN orientation_activities.status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN orientation_activities.status = 'scheduled' THEN 1 ELSE 0 END) as scheduled")
            ->selectRaw("SUM(CASE WHEN orientation_activities.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress")
            ->selectRaw("SUM(CASE WHEN orientation_activities.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled")
            ->first();

        $evidenceCount = OrientationEvidence::query()
            ->join('orientation_actions', 'orientation_actions.id', '=', 'orientation_evidences.orientation_action_id')
            ->where('orientation_actions.orientation_plan_id', $plan->id)
            ->count();

        $actionTotal = (int) ($totals->total ?? 0);
        $traceability = [
            $this->coverage('Responsables definidos', (int) ($totals->with_responsible ?? 0), $actionTotal),
            $this->coverage('Medios de verificación', (int) ($totals->with_verification ?? 0), $actionTotal),
            $this->coverage('Evidencia registrada', (int) ($totals->with_evidence ?? 0), $actionTotal),
            $this->coverage('Planes relacionados', (int) ($totals->with_related_plan ?? 0), $actionTotal),
            $this->coverage('Fechas planificadas', (int) ($totals->with_schedule ?? 0), $actionTotal),
        ];

        return [
            'summary' => [
                'actions' => $actionTotal,
                'completed_actions' => (int) ($totals->completed ?? 0),
                'in_progress_actions' => (int) ($totals->in_progress ?? 0),
                'average_progress' => (int) ($totals->average_progress ?? 0),
                'activities' => (int) ($activityTotals->total ?? 0),
                'completed_activities' => (int) ($activityTotals->completed ?? 0),
                'evidences' => $evidenceCount,
                'related_plans' => $plan->relatedPlans()->count(),
                'overdue_actions' => (int) ($totals->overdue ?? 0),
                'unscheduled_actions' => (int) ($totals->unscheduled ?? 0),
                'traceability' => $traceability
                    ? (int) round(collect($traceability)->avg('percent'))
                    : 0,
            ],
            'action_statuses' => collect(self::ACTION_STATUS_LABELS)->map(fn (string $label, string $status): array => [
                'status' => $status,
                'label' => $label,
                'count' => (int) ($totals->{$status} ?? 0),
            ])->values(),
            'activity_statuses' => collect(self::ACTIVITY_STATUS_LABELS)->map(fn (string $label, string $status): array => [
                'status' => $status,
                'label' => $label,
                'count' => (int) ($activityTotals->{$status} ?? 0),
            ])->values(),
            'traceability' => $traceability,
            'evidence_types' => $this->evidenceTypes($plan),
            'monthly' => $this->monthlyWorkload($plan),
            'action_performance' => $this->actionPerformance($actions),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function evidenceTypes(OrientationPlan $plan): array
    {
        $rows = OrientationEvidence::query()
            ->join('orientation_actions', 'orientation_actions.id', '=', 'orientation_evidences.orientation_action_id')
            ->where('orientation_actions.orientation_plan_id', $plan->id)
            ->groupBy('orientation_evidences.evidence_type')
            ->select('orientation_evidences.evidence_type')
            ->selectRaw('COUNT(*) as total')
            ->orderByDesc('total')
            ->get();

        return $rows->map(fn ($row): array => [
            'type' => $row->evidence_type,
            'label' => self::EVIDENCE_TYPE_LABELS[$row->evidence_type] ?? ucfirst((string) $row->evidence_type),
            'count' => (int) $row->total,
        ])->values()->all();
    }

    private function monthlyWorkload(OrientationPlan $plan): array
    {
        $actionMonths = OrientationAction::query()
            ->where('orientation_plan_id', $plan->id)
            ->whereNotNull('start_date')
            ->selectRaw($this->monthExpression('start_date').' as month_number, COUNT(*) as total')
            ->groupBy('month_number')
            ->pluck('total', 'month_number');

        $activityMonths = OrientationActivity::query()
            ->join('orientation_actions', 'orientation_actions.id', '=', 'orientation_activities.orientation_action_id')
            ->where('orientation_actions.orientation_plan_id', $plan->id)
            ->selectRaw($this->monthExpression('orientation_activities.starts_at').' as month_number, COUNT(*) as total')
            ->groupBy('month_number')
            ->pluck('total', 'month_number');

        return collect(self::MONTHS)->map(fn (string $label, int $month): array => [
            'month' => $month,
            'label' => $label,
            'actions' => (int) ($actionMonths[$month] ?? 0),
            'activities' => (int) ($activityMonths[$month] ?? 0),
        ])->values()->all();
    }

    private function actionPerformance(Builder $actions): array
    {
        return (clone $actions)
            ->select([
                'id', 'title', 'status', 'progress', 'start_date', 'end_date',
                'target_levels', 'responsible_summary', 'sort_order',
            ])
            ->withCount([
                'activities',
                'activities as completed_activities_count' => fn ($query) => $query->where('status', 'completed'),
                'evidences',
                'relatedPlans',
            ])
            ->withSum('activities as activity_contribution_allocated', 'contribution_percent')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (OrientationAction $action): array => [
                'id' => $action->id,
                'title' => $action->title,
                'status' => $action->status,
                'progress' => (int) $action->progress,
                'start_date' => optional($action->start_date)->toDateString(),
                'end_date' => optional($action->end_date)->toDateString(),
                'target_levels' => $action->target_levels,
                'responsible_summary' => $action->responsible_summary,
                'activities_count' => (int) $action->activities_count,
                'completed_activities_count' => (int) $action->completed_activities_count,
                'evidences_count' => (int) $action->evidences_count,
                'related_plans_count' => (int) $action->related_plans_count,
                'activity_contribution_allocated' => (int) ($action->activity_contribution_allocated ?? 0),
                'activity_contribution_remaining' => max(0, 100 - (int) ($action->activity_contribution_allocated ?? 0)),
            ])->values()->all();
    }

    private function coverage(string $label, int $covered, int $total): array
    {
        return [
            'label' => $label,
            'covered' => $covered,
            'total' => $total,
            'percent' => $total ? (int) round(($covered / $total) * 100) : 0,
        ];
    }

    private function monthExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%m', {$column}) AS INTEGER)"
            : "MONTH({$column})";
    }
}
