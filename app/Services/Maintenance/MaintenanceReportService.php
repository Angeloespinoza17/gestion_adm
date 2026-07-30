<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceAnnualPlan;
use App\Models\MaintenanceDependency;
use App\Models\MaintenanceVisit;
use App\Models\MaintenanceWorkOrder;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MaintenanceReportService
{
    private const CLOSED_WORK_ORDER_STATUSES = ['Terminado', 'Anulado'];

    private const WORK_ORDER_STATUSES = ['Sin comenzar', 'En proceso', 'En espera', 'Pausado', 'Terminado', 'Anulado'];

    private const PRIORITIES = ['Crítico', 'Alta', 'Media', 'Baja'];

    public function build(array $filters): array
    {
        [$startDate, $endDate] = $this->resolveRange($filters);
        [$previousStart, $previousEnd] = $this->previousRange($startDate, $endDate);

        $workOrders = $this->workOrderQuery($startDate, $endDate, $filters)
            ->with([
                'dependency:id,code,name,distribution,sector,zone,usage',
                'technicalArea:id,code,name,parent_dependency_id,distribution,sector,zone,usage',
                'inventoryItem:id,code,name,dependency_id,status,condition',
            ])
            ->orderByDesc('reported_at')
            ->orderByDesc('created_at')
            ->get();

        $previousWorkOrders = $this->workOrderQuery($previousStart, $previousEnd, $filters)->get();
        $visits = $this->visitQuery($startDate, $endDate, $filters)
            ->with('dependency:id,code,name,distribution,sector,zone,usage')
            ->orderBy('visit_date')
            ->get();
        $plans = $this->annualPlanQuery($startDate, $endDate, $filters)
            ->with([
                'dependency:id,code,name,distribution,sector,zone,usage',
                'technicalArea:id,code,name,parent_dependency_id,distribution,sector,zone,usage',
                'inventoryItem:id,code,name,dependency_id,status,condition',
            ])
            ->get()
            ->filter(fn (MaintenanceAnnualPlan $plan) => $this->planDate($plan)->betweenIncluded($startDate, $endDate))
            ->values();

        $summary = $this->workOrderSummary($workOrders);
        $previousSummary = $this->workOrderSummary($previousWorkOrders);
        $rankings = [
            'dependencies' => $this->groupWorkOrders(
                $workOrders,
                fn (MaintenanceWorkOrder $item) => $this->dependencyLabel($item)
            ),
            'technical_areas' => $this->groupWorkOrders(
                $workOrders,
                fn (MaintenanceWorkOrder $item) => $item->technicalArea
                    ? "{$item->technicalArea->code} · {$item->technicalArea->name}"
                    : 'Sin área técnica'
            ),
            'assignees' => $this->groupByAssignee($workOrders),
            'requesters' => $this->groupWorkOrders(
                $workOrders,
                fn (MaintenanceWorkOrder $item) => $item->requested_by ?: 'Sin solicitante'
            ),
            'components' => $this->groupWorkOrders(
                $workOrders,
                fn (MaintenanceWorkOrder $item) => $this->componentLabel($item)
            ),
        ];
        $visitSummary = $this->visitSummary($visits);
        $planSummary = $this->planSummary($plans);

        return [
            'generated_at' => now()->toIso8601String(),
            'range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
                'period' => $filters['period'] ?? 'mensual',
                'days' => $startDate->diffInDays($endDate) + 1,
                'comparison_start' => $previousStart->toDateString(),
                'comparison_end' => $previousEnd->toDateString(),
            ],
            'summary' => [
                ...$summary,
                'visits_total' => $visitSummary['total'],
                'visits_completed_total' => $visitSummary['completed'],
                'plans_total' => $planSummary['total'],
                'plans_completed_total' => $planSummary['completed'],
                'plans_overdue_total' => $planSummary['overdue'],
            ],
            'comparison' => [
                'summary' => $previousSummary,
                'deltas' => $this->summaryDeltas($summary, $previousSummary),
            ],
            'charts' => [
                'volume_timeline' => $this->volumeTimeline($workOrders, $startDate, $endDate),
                'orders_by_status' => $this->distribution($workOrders, 'status', self::WORK_ORDER_STATUSES),
                'orders_by_priority' => $this->distribution($workOrders, 'priority', self::PRIORITIES),
                'visits_by_status' => $this->distribution($visits, 'status', ['Programada', 'En progreso', 'Finalizada', 'Cancelada']),
                'plans_by_status' => $this->distribution($plans, 'status', ['Programada', 'En ejecución', 'Cumplida', 'Vencida', 'Cancelada']),
                'plans_timeline' => $this->planTimeline($plans, $startDate, $endDate),
            ],
            'rankings' => collect($rankings)->map(fn (Collection $rows) => $rows->values()->all())->all(),
            'visits' => [
                'summary' => $visitSummary,
                'rows' => $visits->map(fn (MaintenanceVisit $visit) => $this->serializeVisit($visit))->values()->all(),
            ],
            'annual_plan' => [
                'summary' => $planSummary,
                'rows' => $plans
                    ->sortBy(fn (MaintenanceAnnualPlan $plan) => $this->planDate($plan)->timestamp)
                    ->map(fn (MaintenanceAnnualPlan $plan) => $this->serializePlan($plan))
                    ->values()
                    ->all(),
            ],
            'priority_work_orders' => $this->priorityWorkOrders($workOrders),
            'work_orders' => $workOrders
                ->take(5000)
                ->map(fn (MaintenanceWorkOrder $workOrder) => $this->serializeWorkOrder($workOrder))
                ->values()
                ->all(),
            'catalogs' => $this->catalogs(),
            'metadata' => [
                'costs_included' => false,
                'detail_limit' => 5000,
                'detail_truncated' => $workOrders->count() > 5000,
                'completion_time_basis' => 'updated_at',
                'completion_time_note' => 'El tiempo de resolución usa la última actualización de las OT terminadas.',
                'assignee_counting_note' => 'Una OT con más de un responsable cuenta como participación para cada persona asignada.',
            ],
        ];
    }

    private function workOrderQuery(Carbon $startDate, Carbon $endDate, array $filters): Builder
    {
        return MaintenanceWorkOrder::query()
            ->where(function (Builder $query) use ($startDate, $endDate) {
                $query
                    ->whereBetween('reported_at', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhere(function (Builder $fallback) use ($startDate, $endDate) {
                        $fallback
                            ->whereNull('reported_at')
                            ->whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()]);
                    });
            })
            ->when(! empty($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['priority']), fn (Builder $query) => $query->where('priority', $filters['priority']))
            ->when(! empty($filters['dependency_id']), fn (Builder $query) => $query->where('maintenance_dependency_id', $filters['dependency_id']))
            ->when(! empty($filters['technical_area_id']), fn (Builder $query) => $query->where('technical_area_id', $filters['technical_area_id']))
            ->when(! empty($filters['assignee']), fn (Builder $query) => $this->whereAssignedTo($query, $filters['assignee']));
    }

    private function visitQuery(Carbon $startDate, Carbon $endDate, array $filters): Builder
    {
        return MaintenanceVisit::query()
            ->whereBetween('visit_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when(! empty($filters['dependency_id']), fn (Builder $query) => $query->where('maintenance_dependency_id', $filters['dependency_id']))
            ->when(! empty($filters['assignee']), fn (Builder $query) => $query->where('responsible', $filters['assignee']));
    }

    private function annualPlanQuery(Carbon $startDate, Carbon $endDate, array $filters): Builder
    {
        return MaintenanceAnnualPlan::query()
            ->whereBetween('planned_year', [$startDate->year, $endDate->year])
            ->when(! empty($filters['dependency_id']), fn (Builder $query) => $query->where('maintenance_dependency_id', $filters['dependency_id']))
            ->when(! empty($filters['technical_area_id']), fn (Builder $query) => $query->where('technical_area_id', $filters['technical_area_id']))
            ->when(! empty($filters['assignee']), fn (Builder $query) => $query->where('responsible', $filters['assignee']));
    }

    private function workOrderSummary(Collection $workOrders): array
    {
        $today = Carbon::today();
        $open = $workOrders->whereNotIn('status', self::CLOSED_WORK_ORDER_STATUSES);
        $completed = $workOrders->where('status', 'Terminado');
        $completedWithDueDate = $completed->filter(fn (MaintenanceWorkOrder $item) => $item->due_date);
        $onTime = $completedWithDueDate->filter(
            fn (MaintenanceWorkOrder $item) => $item->updated_at
                && $item->updated_at->lte($item->due_date->copy()->endOfDay())
        );
        $resolutionHours = $completed
            ->filter(fn (MaintenanceWorkOrder $item) => $item->updated_at)
            ->map(function (MaintenanceWorkOrder $item): float {
                $start = $item->reported_at
                    ? $item->reported_at->copy()->startOfDay()
                    : $item->created_at;

                return round($start->diffInMinutes($item->updated_at) / 60, 2);
            })
            ->sort()
            ->values();

        return [
            'work_orders_total' => $workOrders->count(),
            'open_total' => $open->count(),
            'completed_total' => $completed->count(),
            'cancelled_total' => $workOrders->where('status', 'Anulado')->count(),
            'overdue_open_total' => $open
                ->filter(fn (MaintenanceWorkOrder $item) => $item->due_date && $item->due_date->lt($today))
                ->count(),
            'critical_open_total' => $open->where('priority', 'Crítico')->count(),
            'high_priority_open_total' => $open->whereIn('priority', ['Crítico', 'Alta'])->count(),
            'unassigned_open_total' => $open
                ->filter(fn (MaintenanceWorkOrder $item) => trim((string) $item->assigned_to) === '')
                ->count(),
            'completion_rate' => $workOrders->isEmpty()
                ? 0
                : round(($completed->count() / $workOrders->count()) * 100, 1),
            'on_time_rate' => $completedWithDueDate->isEmpty()
                ? 0
                : round(($onTime->count() / $completedWithDueDate->count()) * 100, 1),
            'average_resolution_hours' => $resolutionHours->isEmpty()
                ? 0
                : round((float) $resolutionHours->average(), 1),
            'median_resolution_hours' => $resolutionHours->isEmpty()
                ? 0
                : round((float) $resolutionHours->median(), 1),
            'average_open_age_days' => $open->isEmpty()
                ? 0
                : round((float) $open->average(function (MaintenanceWorkOrder $item) use ($today): int {
                    $start = $item->reported_at ?: $item->created_at;

                    return $start->copy()->startOfDay()->diffInDays($today);
                }), 1),
        ];
    }

    private function summaryDeltas(array $summary, array $previous): array
    {
        return collect([
            'work_orders_total',
            'open_total',
            'completed_total',
            'overdue_open_total',
            'critical_open_total',
            'completion_rate',
            'on_time_rate',
            'average_resolution_hours',
        ])->mapWithKeys(function (string $key) use ($summary, $previous): array {
            $currentValue = (float) ($summary[$key] ?? 0);
            $previousValue = (float) ($previous[$key] ?? 0);

            return [$key => $previousValue == 0.0
                ? ($currentValue == 0.0 ? 0.0 : null)
                : round((($currentValue - $previousValue) / abs($previousValue)) * 100, 1)];
        })->all();
    }

    private function groupWorkOrders(Collection $workOrders, callable $labelResolver): Collection
    {
        $total = max(1, $workOrders->count());

        return $workOrders
            ->groupBy($labelResolver)
            ->map(fn (Collection $items, string $label) => $this->rankingRow($label, $items, $total))
            ->sortByDesc('total')
            ->values();
    }

    private function groupByAssignee(Collection $workOrders): Collection
    {
        $participations = $workOrders->flatMap(function (MaintenanceWorkOrder $item): array {
            $assignees = $this->parseAssignees($item->assigned_to);

            return collect($assignees ?: ['Sin asignar'])
                ->map(fn (string $label) => ['label' => $label, 'work_order' => $item])
                ->all();
        });
        $total = max(1, $participations->count());

        return $participations
            ->groupBy('label')
            ->map(function (Collection $items, string $label) use ($total): array {
                return $this->rankingRow($label, $items->pluck('work_order'), $total);
            })
            ->sortByDesc('total')
            ->values();
    }

    private function rankingRow(string $label, Collection $items, int $total): array
    {
        $open = $items->whereNotIn('status', self::CLOSED_WORK_ORDER_STATUSES);

        return [
            'label' => $label,
            'total' => $items->count(),
            'open' => $open->count(),
            'completed' => $items->where('status', 'Terminado')->count(),
            'overdue' => $open->filter(
                fn (MaintenanceWorkOrder $item) => $item->due_date && $item->due_date->lt(Carbon::today())
            )->count(),
            'critical' => $open->where('priority', 'Crítico')->count(),
            'share' => round(($items->count() / $total) * 100, 1),
        ];
    }

    private function visitSummary(Collection $visits): array
    {
        return [
            'total' => $visits->count(),
            'scheduled' => $visits->where('status', 'Programada')->count(),
            'in_progress' => $visits->where('status', 'En progreso')->count(),
            'completed' => $visits->where('status', 'Finalizada')->count(),
            'cancelled' => $visits->where('status', 'Cancelada')->count(),
            'completion_rate' => $visits->isEmpty()
                ? 0
                : round(($visits->where('status', 'Finalizada')->count() / $visits->count()) * 100, 1),
        ];
    }

    private function planSummary(Collection $plans): array
    {
        $active = $plans->whereNotIn('status', ['Cumplida', 'Cancelada']);
        $overdue = $active->filter(
            fn (MaintenanceAnnualPlan $plan) => $plan->scheduled_date && $plan->scheduled_date->lt(Carbon::today())
        );

        return [
            'total' => $plans->count(),
            'scheduled' => $plans->where('status', 'Programada')->count(),
            'in_progress' => $plans->where('status', 'En ejecución')->count(),
            'completed' => $plans->where('status', 'Cumplida')->count(),
            'overdue' => $overdue->count(),
            'cancelled' => $plans->where('status', 'Cancelada')->count(),
            'completion_rate' => $plans->isEmpty()
                ? 0
                : round(($plans->where('status', 'Cumplida')->count() / $plans->count()) * 100, 1),
        ];
    }

    private function volumeTimeline(Collection $workOrders, Carbon $startDate, Carbon $endDate): array
    {
        $monthly = $startDate->diffInDays($endDate) > 62;
        $buckets = [];
        $cursor = $monthly ? $startDate->copy()->startOfMonth() : $startDate->copy();
        $last = $monthly ? $endDate->copy()->startOfMonth() : $endDate->copy();

        while ($cursor->lte($last)) {
            $key = $cursor->format($monthly ? 'Y-m' : 'Y-m-d');
            $buckets[$key] = [
                'label' => $key,
                'total' => 0,
                'completed' => 0,
                'open' => 0,
            ];
            $monthly ? $cursor->addMonth() : $cursor->addDay();
        }

        foreach ($workOrders as $workOrder) {
            $date = $workOrder->reported_at ?: $workOrder->created_at;
            $key = $date->format($monthly ? 'Y-m' : 'Y-m-d');
            if (! isset($buckets[$key])) {
                continue;
            }

            $buckets[$key]['total']++;
            $workOrder->status === 'Terminado'
                ? $buckets[$key]['completed']++
                : ($workOrder->status !== 'Anulado' ? $buckets[$key]['open']++ : null);
        }

        return array_values($buckets);
    }

    private function planTimeline(Collection $plans, Carbon $startDate, Carbon $endDate): array
    {
        $buckets = [];
        $cursor = $startDate->copy()->startOfMonth();
        $last = $endDate->copy()->startOfMonth();

        while ($cursor->lte($last)) {
            $key = $cursor->format('Y-m');
            $buckets[$key] = ['label' => $key, 'total' => 0, 'completed' => 0, 'overdue' => 0];
            $cursor->addMonth();
        }

        foreach ($plans as $plan) {
            $key = $this->planDate($plan)->format('Y-m');
            if (! isset($buckets[$key])) {
                continue;
            }

            $buckets[$key]['total']++;
            $buckets[$key]['completed'] += $plan->status === 'Cumplida' ? 1 : 0;
            $buckets[$key]['overdue'] += $this->isPlanOverdue($plan) ? 1 : 0;
        }

        return array_values($buckets);
    }

    private function distribution(Collection $items, string $field, array $orderedLabels): array
    {
        $counts = $items->countBy($field);

        return collect($orderedLabels)
            ->map(fn (string $label) => ['label' => $label, 'total' => (int) ($counts[$label] ?? 0)])
            ->filter(fn (array $row) => $row['total'] > 0)
            ->values()
            ->all();
    }

    private function priorityWorkOrders(Collection $workOrders): array
    {
        $priorityOrder = array_flip(self::PRIORITIES);

        return $workOrders
            ->whereNotIn('status', self::CLOSED_WORK_ORDER_STATUSES)
            ->sort(function (MaintenanceWorkOrder $left, MaintenanceWorkOrder $right) use ($priorityOrder): int {
                $leftOverdue = $left->due_date && $left->due_date->lt(Carbon::today()) ? 0 : 1;
                $rightOverdue = $right->due_date && $right->due_date->lt(Carbon::today()) ? 0 : 1;

                return [$leftOverdue, $priorityOrder[$left->priority] ?? 99, $left->due_date?->timestamp ?? PHP_INT_MAX]
                    <=> [$rightOverdue, $priorityOrder[$right->priority] ?? 99, $right->due_date?->timestamp ?? PHP_INT_MAX];
            })
            ->take(12)
            ->map(fn (MaintenanceWorkOrder $workOrder) => $this->serializeWorkOrder($workOrder))
            ->values()
            ->all();
    }

    private function serializeWorkOrder(MaintenanceWorkOrder $workOrder): array
    {
        $reportedAt = $workOrder->reported_at ?: $workOrder->created_at;

        return [
            'id' => $workOrder->id,
            'reported_at' => optional($reportedAt)->toDateString(),
            'due_date' => optional($workOrder->due_date)->toDateString(),
            'requested_by' => $workOrder->requested_by,
            'assigned_to' => $workOrder->assigned_to,
            'priority' => $workOrder->priority,
            'status' => $workOrder->status,
            'description' => $workOrder->description,
            'resolution_notes' => $workOrder->resolution_notes,
            'dependency' => $this->dependencyLabel($workOrder),
            'technical_area' => $workOrder->technicalArea
                ? "{$workOrder->technicalArea->code} · {$workOrder->technicalArea->name}"
                : null,
            'component' => $this->componentLabel($workOrder),
            'overdue' => ! in_array($workOrder->status, self::CLOSED_WORK_ORDER_STATUSES, true)
                && $workOrder->due_date
                && $workOrder->due_date->lt(Carbon::today()),
        ];
    }

    private function serializeVisit(MaintenanceVisit $visit): array
    {
        return [
            'id' => $visit->id,
            'visit_date' => optional($visit->visit_date)->toDateString(),
            'visit_time' => $visit->visit_time,
            'visit_type' => $visit->visit_type,
            'status' => $visit->status,
            'responsible' => $visit->responsible,
            'dependency' => $visit->dependency
                ? "{$visit->dependency->code} · {$visit->dependency->name}"
                : 'Sin dependencia',
            'notes' => $visit->notes,
        ];
    }

    private function serializePlan(MaintenanceAnnualPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'planned_date' => $this->planDate($plan)->toDateString(),
            'scheduled_date' => optional($plan->scheduled_date)->toDateString(),
            'completed_date' => optional($plan->completed_date)->toDateString(),
            'title' => $plan->title,
            'category' => $plan->category,
            'frequency' => $plan->frequency,
            'status' => $plan->status,
            'responsible' => $plan->responsible,
            'dependency' => $plan->dependency
                ? "{$plan->dependency->code} · {$plan->dependency->name}"
                : 'Sin dependencia',
            'technical_area' => $plan->technicalArea
                ? "{$plan->technicalArea->code} · {$plan->technicalArea->name}"
                : null,
            'overdue' => $this->isPlanOverdue($plan),
        ];
    }

    private function catalogs(): array
    {
        $staffAssignees = Staff::query()
            ->where('active', true)
            ->where('can_receive_maintenance_orders', true)
            ->orderBy('full_name')
            ->pluck('full_name');
        $historicalAssignees = MaintenanceWorkOrder::query()
            ->whereNotNull('assigned_to')
            ->pluck('assigned_to')
            ->flatMap(fn (?string $value) => $this->parseAssignees($value));

        return [
            'statuses' => self::WORK_ORDER_STATUSES,
            'priorities' => self::PRIORITIES,
            'assignees' => $staffAssignees
                ->merge($historicalAssignees)
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all(),
            'dependencies' => MaintenanceDependency::query()
                ->maintenanceLocations()
                ->where('active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'distribution', 'sector', 'zone']),
            'technical_areas' => MaintenanceDependency::query()
                ->technicalAssets()
                ->where('active', true)
                ->orderBy('code')
                ->get(['id', 'parent_dependency_id', 'code', 'name', 'distribution', 'sector', 'zone']),
        ];
    }

    private function dependencyLabel(MaintenanceWorkOrder $workOrder): string
    {
        if ($workOrder->dependency) {
            return "{$workOrder->dependency->code} · {$workOrder->dependency->name}";
        }

        $legacy = collect([$workOrder->location_code, $workOrder->location_name])
            ->filter()
            ->implode(' · ');

        return $legacy ?: 'Sin dependencia';
    }

    private function componentLabel(MaintenanceWorkOrder $workOrder): string
    {
        if ($workOrder->inventoryItem) {
            return "{$workOrder->inventoryItem->code} · {$workOrder->inventoryItem->name}";
        }

        return $workOrder->dependency_component ?: 'Sin elemento identificado';
    }

    private function planDate(MaintenanceAnnualPlan $plan): Carbon
    {
        return $plan->scheduled_date
            ? $plan->scheduled_date->copy()
            : Carbon::create((int) $plan->planned_year, (int) $plan->planned_month, 1)->startOfDay();
    }

    private function isPlanOverdue(MaintenanceAnnualPlan $plan): bool
    {
        return ! in_array($plan->status, ['Cumplida', 'Cancelada'], true)
            && $plan->scheduled_date
            && $plan->scheduled_date->lt(Carbon::today());
    }

    private function parseAssignees(?string $value): array
    {
        return collect(preg_split('/\s*,\s*/u', trim((string) $value)) ?: [])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function whereAssignedTo(Builder $query, string $assignee): Builder
    {
        if ($assignee === 'Sin asignar') {
            return $query->where(fn (Builder $inner) => $inner->whereNull('assigned_to')->orWhere('assigned_to', ''));
        }

        return $query->where(function (Builder $inner) use ($assignee) {
            $inner
                ->where('assigned_to', $assignee)
                ->orWhere('assigned_to', 'like', "{$assignee}, %")
                ->orWhere('assigned_to', 'like', "%, {$assignee}")
                ->orWhere('assigned_to', 'like', "%, {$assignee}, %");
        });
    }

    private function resolveRange(array $filters): array
    {
        if (! empty($filters['start_date']) || ! empty($filters['end_date'])) {
            $start = Carbon::parse($filters['start_date'] ?? $filters['end_date'])->startOfDay();
            $end = Carbon::parse($filters['end_date'] ?? $filters['start_date'])->endOfDay();

            return [$start, $end];
        }

        $today = Carbon::today();

        return match ($filters['period'] ?? 'mensual') {
            'diario' => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            'semanal' => [$today->copy()->startOfWeek(), $today->copy()->endOfDay()],
            'semestral' => [
                $today->month <= 6
                    ? $today->copy()->startOfYear()
                    : $today->copy()->month(7)->startOfMonth(),
                $today->copy()->endOfDay(),
            ],
            'anual' => [$today->copy()->startOfYear(), $today->copy()->endOfDay()],
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfDay()],
        };
    }

    private function previousRange(Carbon $startDate, Carbon $endDate): array
    {
        $days = $startDate->diffInDays($endDate) + 1;
        $previousEnd = $startDate->copy()->subDay()->endOfDay();

        return [$previousEnd->copy()->subDays($days - 1)->startOfDay(), $previousEnd];
    }
}
