<?php

namespace App\Services\CentroApuntes;

use App\Models\CentroApuntes\CentroApuntesSolicitud;
use App\Models\CentroApuntes\PanolEntrega;
use App\Models\CentroApuntes\PanolMovimiento;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class CentroApuntesReportService
{
    public function build(array $filters): array
    {
        [$startDate, $endDate] = $this->resolveRange($filters);

        $requestsQuery = CentroApuntesSolicitud::query()
            ->whereBetween('requested_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()]);
        $deliveriesQuery = PanolEntrega::query()
            ->whereBetween('requested_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()]);
        $movementsQuery = PanolMovimiento::query()
            ->whereBetween('moved_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()]);

        $this->applyRequestFilters($requestsQuery, $filters);
        $this->applyDeliveryFilters($deliveriesQuery, $filters);
        $this->applyMovementFilters($movementsQuery, $filters);

        $requests = $requestsQuery->with(['requester:id,name', 'subject:id,name', 'machine:id,name'])->orderByDesc('requested_at')->get();
        $deliveries = $deliveriesQuery->with(['requester:id,name', 'department:id,name', 'details'])->orderByDesc('requested_at')->get();
        $movements = $movementsQuery->with(['insumo:id,name,category,unit_of_measure', 'department:id,name'])->orderByDesc('moved_at')->get();

        return [
            'range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
                'period' => $filters['period'] ?? 'mensual',
            ],
            'summary' => [
                'requests_total' => $requests->count(),
                'urgent_total' => $requests->where('is_urgent', true)->count(),
                'immediate_total' => $requests->where('is_immediate', true)->count(),
                'delivered_total' => $requests->where('status', 'entregada')->count(),
                'supplies_out_total' => round((float) $movements->whereIn('movement_type', ['salida', 'perdida', 'vencimiento', 'baja'])->sum('quantity'), 2),
                'deliveries_total' => $deliveries->count(),
            ],
            'charts' => [
                'requests_by_status' => $requests->groupBy('status')->map(fn ($items, $label) => ['label' => $label, 'total' => $items->count()])->values()->all(),
                'requests_by_machine' => $requests->groupBy('machine_name_snapshot')->map(fn ($items, $label) => ['label' => $label ?: 'Sin máquina', 'total' => $items->count()])->values()->sortByDesc('total')->values()->take(10)->all(),
                'requests_by_subject' => $requests->groupBy('subject_name_snapshot')->map(fn ($items, $label) => ['label' => $label ?: 'Sin asignatura', 'total' => $items->count()])->values()->sortByDesc('total')->values()->take(10)->all(),
                'deliveries_by_area' => $deliveries->groupBy('department_name_snapshot')->map(fn ($items, $label) => ['label' => $label ?: 'Sin área', 'total' => $items->count()])->values()->sortByDesc('total')->values()->take(10)->all(),
                'supplies_by_category' => $movements->groupBy(fn ($movement) => $movement->insumo?->category ?: 'otro')->map(fn ($items, $label) => ['label' => $label, 'total' => round((float) $items->sum('quantity'), 2)])->values()->sortByDesc('total')->values()->take(10)->all(),
            ],
            'sections' => [
                [
                    'title' => 'Solicitudes de impresión',
                    'headers' => ['Código', 'Solicitante', 'Asignatura', 'Máquina', 'Tipo', 'Estado', 'Entrega'],
                    'rows' => $requests->map(fn (CentroApuntesSolicitud $item) => [
                        $item->request_code,
                        $item->requested_by_name_snapshot,
                        $item->subject_name_snapshot,
                        $item->machine_name_snapshot,
                        $item->task_type === 'otro' ? ($item->task_type_other ?: 'Otro') : str($item->task_type)->replace('_', ' ')->title()->toString(),
                        str($item->status)->replace('_', ' ')->title()->toString(),
                        optional($item->delivery_date)->format('Y-m-d'),
                    ])->all(),
                ],
                [
                    'title' => 'Entregas de materiales',
                    'headers' => ['Código', 'Solicitante', 'Área', 'Estado', 'Fecha'],
                    'rows' => $deliveries->map(fn (PanolEntrega $item) => [
                        $item->delivery_code,
                        $item->requested_by_name_snapshot,
                        $item->department_name_snapshot ?: 'Sin área',
                        str($item->status)->replace('_', ' ')->title()->toString(),
                        optional($item->requested_at)->format('Y-m-d H:i'),
                    ])->all(),
                ],
                [
                    'title' => 'Movimientos de stock',
                    'headers' => ['Fecha', 'Insumo', 'Categoría', 'Tipo', 'Cantidad', 'Área', 'Motivo'],
                    'rows' => $movements->map(fn (PanolMovimiento $item) => [
                        optional($item->moved_at)->format('Y-m-d H:i'),
                        $item->insumo?->name,
                        $item->insumo?->category,
                        str($item->movement_type)->replace('_', ' ')->title()->toString(),
                        $item->quantity,
                        $item->department?->name,
                        $item->reason,
                    ])->all(),
                ],
            ],
        ];
    }

    private function resolveRange(array $filters): array
    {
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            return [
                Carbon::parse($filters['start_date']),
                Carbon::parse($filters['end_date']),
            ];
        }

        $today = Carbon::today();
        $period = $filters['period'] ?? 'mensual';

        return match ($period) {
            'diario' => [$today->copy(), $today->copy()],
            'semanal' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            'mensual' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'semestral' => [$today->copy()->subMonths(5)->startOfMonth(), $today->copy()->endOfMonth()],
            'anual' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
        };
    }

    private function applyRequestFilters(Builder $query, array $filters): void
    {
        $query
            ->when(!empty($filters['requested_by_user_id']), fn (Builder $builder) => $builder->where('requested_by_user_id', $filters['requested_by_user_id']))
            ->when(!empty($filters['subject_id']), fn (Builder $builder) => $builder->where('subject_id', $filters['subject_id']))
            ->when(!empty($filters['machine_id']), fn (Builder $builder) => $builder->where('machine_id', $filters['machine_id']))
            ->when(!empty($filters['paper_size']), fn (Builder $builder) => $builder->where('paper_size', $filters['paper_size']))
            ->when(!empty($filters['task_type']), fn (Builder $builder) => $builder->where('task_type', $filters['task_type']))
            ->when(!empty($filters['status']), fn (Builder $builder) => $builder->where('status', $filters['status']))
            ->when(!empty($filters['urgent_only']), fn (Builder $builder) => $builder->where('is_urgent', true))
            ->when(!empty($filters['immediate_only']), fn (Builder $builder) => $builder->where('is_immediate', true));
    }

    private function applyDeliveryFilters(Builder $query, array $filters): void
    {
        $query
            ->when(!empty($filters['requested_by_user_id']), fn (Builder $builder) => $builder->where('requested_by_user_id', $filters['requested_by_user_id']))
            ->when(!empty($filters['department_id']), fn (Builder $builder) => $builder->where('department_id', $filters['department_id']))
            ->when(!empty($filters['status']), fn (Builder $builder) => $builder->where('status', $filters['status']));
    }

    private function applyMovementFilters(Builder $query, array $filters): void
    {
        $query
            ->when(!empty($filters['supply_id']), fn (Builder $builder) => $builder->where('insumo_id', $filters['supply_id']))
            ->when(!empty($filters['department_id']), fn (Builder $builder) => $builder->where('department_id', $filters['department_id']))
            ->when(!empty($filters['category']), function (Builder $builder) use ($filters) {
                $builder->whereHas('insumo', fn (Builder $insumoQuery) => $insumoQuery->where('category', $filters['category']));
            });
    }
}
