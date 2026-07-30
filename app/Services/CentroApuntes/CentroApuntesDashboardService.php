<?php

namespace App\Services\CentroApuntes;

use App\Models\CentroApuntes\CentroApuntesAlerta;
use App\Models\CentroApuntes\CentroApuntesMaquina;
use App\Models\CentroApuntes\CentroApuntesSolicitud;
use App\Models\CentroApuntes\PanolEntrega;
use App\Models\CentroApuntes\PanolInsumo;
use App\Models\CentroApuntes\PanolMovimiento;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CentroApuntesDashboardService
{
    private const CLOSED_REQUEST_STATUSES = ['entregada', 'rechazada', 'anulada'];

    public function build(): array
    {
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $previousMonthStart = $today->copy()->subMonthNoOverflow()->startOfMonth();
        $previousMonthEnd = $previousMonthStart->copy()
            ->addDays($monthStart->diffInDays($today))
            ->min($previousMonthStart->copy()->endOfMonth());

        $monthRequests = $this->requestsForRange($monthStart, $today);
        $previousMonthRequests = $this->requestsForRange($previousMonthStart, $previousMonthEnd);
        $monthSummary = $this->requestSummary($monthRequests);
        $previousSummary = $this->requestSummary($previousMonthRequests);
        $alerts = $this->refreshAlerts($today);
        $queue = $this->requestQueue();

        $metrics = [
            ...$monthSummary,
            'open_tasks' => array_sum($queue),
            'overdue_tasks' => $alerts['overdue_tasks'],
            'urgent_requests' => $alerts['urgent_tasks'],
            'critical_stock' => $alerts['critical_stock'],
            'out_of_stock' => $alerts['out_of_stock'],
            'active_machines' => CentroApuntesMaquina::query()->where('status', 'activa')->count(),
            'machines_in_maintenance' => $alerts['machines_in_maintenance'],
            'delivered_today' => CentroApuntesSolicitud::query()->whereDate('delivered_at', $today)->count(),
            'today_printed_sheets' => (int) CentroApuntesSolicitud::query()
                ->whereBetween('requested_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
                ->sum('estimated_total_impressions'),
            'delivered_materials' => (float) PanolEntrega::query()
                ->join('panol_entrega_detalles', 'panol_entrega_detalles.panol_entrega_id', '=', 'panol_entregas.id')
                ->where('panol_entregas.status', 'entregada')
                ->whereDate('panol_entregas.delivered_at', '>=', $monthStart)
                ->sum('panol_entrega_detalles.quantity'),
            'supplies_near_depletion' => PanolInsumo::query()
                ->where('active', true)
                ->where('current_stock', '>', 0)
                ->whereRaw('current_stock <= CASE WHEN minimum_stock * 1.2 > minimum_stock + 1 THEN minimum_stock * 1.2 ELSE minimum_stock + 1 END')
                ->count(),
            // Compatibilidad con consumidores anteriores del endpoint.
            'pending_tasks' => $queue['pendiente'],
            'in_progress_tasks' => $queue['recibida'] + $queue['en_proceso'] + $queue['pausada'],
            'ready_for_pickup' => $queue['lista_para_retiro'],
            'month_sheets' => $monthSummary['month_printed_sheets'],
            'month_copies' => $monthSummary['month_copy_sets'],
            'month_letter_consumption' => (int) $monthRequests->where('paper_size', 'carta')->sum('estimated_total_impressions'),
            'month_officio_consumption' => (int) $monthRequests->where('paper_size', 'oficio')->sum('estimated_total_impressions'),
        ];

        return [
            'generated_at' => now()->toIso8601String(),
            'period' => [
                'label' => ucfirst($today->locale('es')->translatedFormat('F Y')),
                'start' => $monthStart->toDateString(),
                'end' => $today->toDateString(),
                'comparison_start' => $previousMonthStart->toDateString(),
                'comparison_end' => $previousMonthEnd->toDateString(),
            ],
            'metrics' => $metrics,
            'comparison' => [
                'summary' => $previousSummary,
                'deltas' => $this->summaryDeltas($monthSummary, $previousSummary),
            ],
            'queue' => $queue,
            'alerts' => $alerts,
            'charts' => [
                'production_by_day' => $this->productionByDay($monthRequests, $monthStart, $today),
                'requests_by_status' => $this->requestsByStatus($monthRequests),
                'sheets_by_user' => $this->groupPrintedSheets(
                    $monthRequests,
                    fn (CentroApuntesSolicitud $request) => $request->requested_by_name_snapshot ?: 'Sin funcionario',
                ),
                'sheets_by_department' => $this->groupPrintedSheets(
                    $monthRequests,
                    fn (CentroApuntesSolicitud $request) => $request->department_name_snapshot ?: 'Sin departamento',
                ),
                'sheets_by_subject' => $this->groupPrintedSheets(
                    $monthRequests,
                    fn (CentroApuntesSolicitud $request) => $request->subject_name_snapshot ?: 'Sin asignatura',
                ),
                'sheets_by_machine' => $this->groupPrintedSheets(
                    $monthRequests,
                    fn (CentroApuntesSolicitud $request) => $request->machine_name_snapshot ?: 'Sin máquina',
                ),
                'sheets_by_paper_size' => $this->groupPrintedSheets(
                    $monthRequests,
                    fn (CentroApuntesSolicitud $request) => str($request->paper_size ?: 'sin_formato')->replace('_', ' ')->title()->toString(),
                    4,
                ),
                'supply_consumption' => $this->supplyConsumption($monthStart),
                'critical_stock' => $this->criticalStockChart(),
            ],
            'priority_requests' => $this->priorityRequests($today),
            'inventory_alerts' => $this->inventoryAlerts($today),
            'recent' => [
                'requests' => CentroApuntesSolicitud::query()
                    ->latest('requested_at')
                    ->limit(6)
                    ->get(),
                'deliveries' => PanolEntrega::query()
                    ->with(['department:id,name'])
                    ->latest('requested_at')
                    ->limit(6)
                    ->get(),
                'movements' => PanolMovimiento::query()
                    ->with(['insumo:id,name'])
                    ->latest('moved_at')
                    ->limit(6)
                    ->get(),
            ],
            'metadata' => [
                'costs_included' => false,
            ],
        ];
    }

    private function requestsForRange(Carbon $start, Carbon $end): Collection
    {
        return CentroApuntesSolicitud::query()
            ->whereBetween('requested_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->orderBy('requested_at')
            ->get();
    }

    private function requestSummary(Collection $requests): array
    {
        $delivered = $requests->filter(
            fn (CentroApuntesSolicitud $request) => $request->status === 'entregada' && $request->delivered_at,
        );
        $onTime = $delivered->filter(
            fn (CentroApuntesSolicitud $request) => Carbon::parse($request->delivered_at)
                ->startOfDay()
                ->lte(Carbon::parse($request->delivery_date)->endOfDay()),
        );
        $turnaround = $delivered
            ->map(fn (CentroApuntesSolicitud $request) => round(
                Carbon::parse($request->requested_at)->diffInMinutes(Carbon::parse($request->delivered_at)) / 60,
                1,
            ))
            ->sort()
            ->values();
        $printedSheets = (int) $requests->sum('estimated_total_impressions');

        return [
            'month_requests' => $requests->count(),
            'month_original_pages' => (int) $requests->sum('sheet_count'),
            'month_copy_sets' => (int) $requests->sum('copies_count'),
            'month_printed_sheets' => $printedSheets,
            'month_delivered' => $delivered->count(),
            'on_time_rate' => $delivered->isEmpty() ? 0 : round(($onTime->count() / $delivered->count()) * 100, 1),
            'median_turnaround_hours' => $turnaround->isEmpty() ? 0 : round((float) $turnaround->median(), 1),
            'average_sheets_per_request' => $requests->isEmpty() ? 0 : round($printedSheets / $requests->count(), 1),
        ];
    }

    private function summaryDeltas(array $current, array $previous): array
    {
        return collect([
            'month_requests',
            'month_original_pages',
            'month_copy_sets',
            'month_printed_sheets',
            'month_delivered',
            'on_time_rate',
            'median_turnaround_hours',
            'average_sheets_per_request',
        ])->mapWithKeys(function (string $key) use ($current, $previous): array {
            $currentValue = (float) ($current[$key] ?? 0);
            $previousValue = (float) ($previous[$key] ?? 0);

            return [
                $key => $previousValue == 0.0
                    ? ($currentValue == 0.0 ? 0.0 : null)
                    : round((($currentValue - $previousValue) / abs($previousValue)) * 100, 1),
            ];
        })->all();
    }

    private function requestQueue(): array
    {
        $counts = CentroApuntesSolicitud::query()
            ->whereNotIn('status', self::CLOSED_REQUEST_STATUSES)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'pendiente' => (int) ($counts['pendiente'] ?? 0),
            'recibida' => (int) ($counts['recibida'] ?? 0),
            'en_proceso' => (int) ($counts['en_proceso'] ?? 0),
            'pausada' => (int) ($counts['pausada'] ?? 0),
            'lista_para_retiro' => (int) ($counts['lista_para_retiro'] ?? 0),
        ];
    }

    private function productionByDay(Collection $requests, Carbon $start, Carbon $end): array
    {
        $daily = $requests
            ->groupBy(fn (CentroApuntesSolicitud $request) => $request->requested_at->format('Y-m-d'))
            ->map(fn (Collection $items) => [
                'requests' => $items->count(),
                'printed_sheets' => (int) $items->sum('estimated_total_impressions'),
            ]);
        $labels = [];
        $printedSheets = [];
        $requestTotals = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->format('Y-m-d');
            $labels[] = $date->format('d/m');
            $printedSheets[] = (int) ($daily->get($key)['printed_sheets'] ?? 0);
            $requestTotals[] = (int) ($daily->get($key)['requests'] ?? 0);
        }

        return [
            'labels' => $labels,
            'printed_sheets' => $printedSheets,
            'requests' => $requestTotals,
        ];
    }

    private function requestsByStatus(Collection $requests): array
    {
        $counts = $requests->countBy('status');

        return collect(CentroApuntesSolicitud::STATUS_OPTIONS)
            ->filter(fn (string $status) => ($counts[$status] ?? 0) > 0)
            ->map(fn (string $status) => [
                'label' => str($status)->replace('_', ' ')->title()->toString(),
                'status' => $status,
                'total' => (int) $counts[$status],
            ])
            ->values()
            ->all();
    }

    private function groupPrintedSheets(Collection $requests, callable $labelResolver, int $limit = 8): array
    {
        return $requests
            ->groupBy($labelResolver)
            ->map(fn (Collection $items, string $label) => [
                'label' => $label,
                'requests' => $items->count(),
                'printed_sheets' => (int) $items->sum('estimated_total_impressions'),
                'total' => (int) $items->sum('estimated_total_impressions'),
            ])
            ->sortByDesc('printed_sheets')
            ->take($limit)
            ->values()
            ->all();
    }

    private function supplyConsumption(Carbon $from): array
    {
        return PanolMovimiento::query()
            ->join('panol_insumos', 'panol_insumos.id', '=', 'panol_movimientos.insumo_id')
            ->selectRaw('panol_insumos.name as label, sum(panol_movimientos.quantity) as total')
            ->whereIn('panol_movimientos.movement_type', ['salida', 'perdida', 'vencimiento', 'baja'])
            ->whereDate('panol_movimientos.moved_at', '>=', $from)
            ->groupBy('panol_insumos.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->toArray();
    }

    private function criticalStockChart(): array
    {
        return PanolInsumo::query()
            ->selectRaw('name as label, current_stock as total, minimum_stock')
            ->where('active', true)
            ->whereColumn('current_stock', '<=', 'minimum_stock')
            ->orderBy('current_stock')
            ->limit(8)
            ->get()
            ->toArray();
    }

    private function priorityRequests(Carbon $today): array
    {
        return CentroApuntesSolicitud::query()
            ->whereNotIn('status', self::CLOSED_REQUEST_STATUSES)
            ->orderByRaw(
                'CASE WHEN delivery_date < ? THEN 0 WHEN is_immediate = 1 THEN 1 WHEN is_urgent = 1 THEN 2 ELSE 3 END',
                [$today->toDateString()],
            )
            ->orderBy('delivery_date')
            ->limit(8)
            ->get()
            ->map(fn (CentroApuntesSolicitud $request) => [
                'id' => $request->id,
                'request_code' => $request->request_code,
                'requested_by_name' => $request->requested_by_name_snapshot,
                'department_name' => $request->department_name_snapshot ?: 'Sin departamento',
                'subject_name' => $request->subject_name_snapshot ?: 'Sin asignatura',
                'status' => $request->status,
                'priority' => $request->priority,
                'delivery_date' => optional($request->delivery_date)->toDateString(),
                'printed_sheets' => (int) $request->estimated_total_impressions,
                'is_overdue' => $request->delivery_date?->lt($today) ?? false,
                'is_urgent' => (bool) $request->is_urgent,
                'is_immediate' => (bool) $request->is_immediate,
            ])
            ->all();
    }

    private function inventoryAlerts(Carbon $today): array
    {
        return PanolInsumo::query()
            ->where('active', true)
            ->where(function ($query) use ($today): void {
                $query
                    ->whereColumn('current_stock', '<=', 'minimum_stock')
                    ->orWhereBetween('expires_at', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()]);
            })
            ->orderByRaw('CASE WHEN current_stock <= 0 THEN 0 WHEN current_stock <= minimum_stock THEN 1 ELSE 2 END')
            ->orderBy('current_stock')
            ->limit(8)
            ->get(['id', 'name', 'category', 'unit_of_measure', 'current_stock', 'minimum_stock', 'expires_at', 'status'])
            ->map(fn (PanolInsumo $supply) => [
                'id' => $supply->id,
                'name' => $supply->name,
                'category' => str($supply->category ?: 'otro')->replace('_', ' ')->title()->toString(),
                'unit' => str($supply->unit_of_measure ?: 'unidad')->replace('_', ' ')->title()->toString(),
                'current_stock' => (float) $supply->current_stock,
                'minimum_stock' => (float) $supply->minimum_stock,
                'expires_at' => optional($supply->expires_at)->toDateString(),
                'alert_status' => (float) $supply->current_stock <= 0
                    ? 'agotado'
                    : ((float) $supply->current_stock <= (float) $supply->minimum_stock ? 'stock_bajo' : 'proximo_a_vencer'),
            ])
            ->all();
    }

    private function refreshAlerts(Carbon $today): array
    {
        $alerts = [
            'pending_tasks' => CentroApuntesSolicitud::query()->where('status', 'pendiente')->count(),
            'urgent_tasks' => CentroApuntesSolicitud::query()->where('is_urgent', true)->whereNotIn('status', self::CLOSED_REQUEST_STATUSES)->count(),
            'immediate_deliveries' => CentroApuntesSolicitud::query()->where('is_immediate', true)->whereNotIn('status', self::CLOSED_REQUEST_STATUSES)->count(),
            'overdue_tasks' => CentroApuntesSolicitud::query()->whereDate('delivery_date', '<', $today)->whereNotIn('status', self::CLOSED_REQUEST_STATUSES)->count(),
            'critical_stock' => PanolInsumo::query()->where('active', true)->where('current_stock', '>', 0)->whereColumn('current_stock', '<=', 'minimum_stock')->count(),
            'out_of_stock' => PanolInsumo::query()->where('active', true)->where('current_stock', '<=', 0)->count(),
            'machines_in_maintenance' => DB::table('centro_apuntes_maquinas')->where('status', 'en_mantencion')->count(),
            'ready_for_pickup' => CentroApuntesSolicitud::query()->where('status', 'lista_para_retiro')->count(),
            'supplies_expiring' => PanolInsumo::query()->whereNotNull('expires_at')->whereBetween('expires_at', [$today, $today->copy()->addDays(15)])->count(),
        ];

        $definitions = [
            'pending_tasks' => ['warning', 'Tareas pendientes'],
            'urgent_tasks' => ['danger', 'Solicitudes urgentes'],
            'immediate_deliveries' => ['danger', 'Entregas inmediatas'],
            'overdue_tasks' => ['danger', 'Tareas atrasadas'],
            'critical_stock' => ['warning', 'Stock crítico'],
            'out_of_stock' => ['danger', 'Insumos agotados'],
            'machines_in_maintenance' => ['info', 'Máquinas en mantención'],
            'ready_for_pickup' => ['success', 'Listas para retiro'],
            'supplies_expiring' => ['warning', 'Insumos próximos a vencer'],
        ];

        foreach ($alerts as $key => $value) {
            $activeAlert = CentroApuntesAlerta::query()
                ->where('alert_type', $key)
                ->where('status', 'pendiente')
                ->latest('id')
                ->first();

            if ($value <= 0) {
                if ($activeAlert) {
                    $activeAlert->update([
                        'status' => 'resuelta',
                        'resolved_at' => Carbon::now(),
                        'metadata' => array_merge($activeAlert->metadata ?? [], ['count' => 0]),
                    ]);
                }

                continue;
            }

            $payload = [
                'alert_type' => $key,
                'alert_level' => $definitions[$key][0] ?? 'info',
                'title' => $definitions[$key][1] ?? 'Alerta',
                'message' => sprintf('%s: %s caso(s) detectado(s).', $definitions[$key][1] ?? 'Alerta', $value),
                'status' => 'pendiente',
                'metadata' => ['count' => $value],
                'resolved_at' => null,
            ];

            if ($activeAlert) {
                $activeAlert->update($payload);
            } else {
                CentroApuntesAlerta::query()->create([
                    ...$payload,
                    'detected_at' => Carbon::now(),
                ]);
            }
        }

        return $alerts;
    }
}
