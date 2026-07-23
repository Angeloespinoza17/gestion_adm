<?php

namespace App\Services\CentroApuntes;

use App\Models\CentroApuntes\CentroApuntesAlerta;
use App\Models\CentroApuntes\CentroApuntesSolicitud;
use App\Models\CentroApuntes\PanolEntrega;
use App\Models\CentroApuntes\PanolInsumo;
use App\Models\CentroApuntes\PanolMovimiento;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CentroApuntesDashboardService
{
    public function build(): array
    {
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $yearStart = $today->copy()->startOfYear();

        $metrics = [
            'pending_tasks' => CentroApuntesSolicitud::query()->where('status', 'pendiente')->count(),
            'in_progress_tasks' => CentroApuntesSolicitud::query()->where('status', 'en_proceso')->count(),
            'ready_for_pickup' => CentroApuntesSolicitud::query()->where('status', 'lista_para_retiro')->count(),
            'delivered_today' => CentroApuntesSolicitud::query()->whereDate('delivered_at', $today)->count(),
            'urgent_requests' => CentroApuntesSolicitud::query()->where('is_urgent', true)->whereNotIn('status', ['entregada', 'rechazada', 'anulada'])->count(),
            'month_sheets' => (int) CentroApuntesSolicitud::query()->whereDate('requested_at', '>=', $monthStart)->sum('sheet_count'),
            'month_copies' => (int) CentroApuntesSolicitud::query()->whereDate('requested_at', '>=', $monthStart)->sum('copies_count'),
            'month_letter_consumption' => (int) CentroApuntesSolicitud::query()->whereDate('requested_at', '>=', $monthStart)->where('paper_size', 'carta')->sum('estimated_total_impressions'),
            'month_officio_consumption' => (int) CentroApuntesSolicitud::query()->whereDate('requested_at', '>=', $monthStart)->where('paper_size', 'oficio')->sum('estimated_total_impressions'),
            'critical_stock' => PanolInsumo::query()->where('active', true)->where('current_stock', '>', 0)->whereColumn('current_stock', '<=', 'minimum_stock')->count(),
            'delivered_materials' => (float) PanolEntrega::query()
                ->join('panol_entrega_detalles', 'panol_entrega_detalles.panol_entrega_id', '=', 'panol_entregas.id')
                ->where('panol_entregas.status', 'entregada')
                ->whereDate('panol_entregas.delivered_at', '>=', $monthStart)
                ->sum('panol_entrega_detalles.quantity'),
            'supplies_near_depletion' => PanolInsumo::query()
                ->where('active', true)
                ->where('current_stock', '>', 0)
                ->whereRaw('current_stock <= GREATEST(minimum_stock * 1.2, minimum_stock + 1)')
                ->count(),
        ];

        $alerts = $this->refreshAlerts($today);

        return [
            'generated_at' => now()->toIso8601String(),
            'metrics' => $metrics,
            'alerts' => $alerts,
            'charts' => [
                'requests_by_day' => $this->requestsByDay($monthStart, $today),
                'sheets_by_month' => $this->groupRequestTotalsByMonth($yearStart),
                'copies_by_machine' => $this->copiesByMachine(),
                'requests_by_subject' => $this->requestsBySubject(),
                'requests_by_user' => $this->requestsByUser(),
                'supply_consumption' => $this->supplyConsumption($monthStart),
                'critical_stock' => $this->criticalStockChart(),
                'deliveries_by_area' => $this->deliveriesByArea($monthStart),
            ],
            'recent' => [
                'requests' => CentroApuntesSolicitud::query()
                    ->with(['requester:id,name', 'subject:id,name', 'machine:id,name'])
                    ->latest('requested_at')
                    ->limit(8)
                    ->get(),
                'deliveries' => PanolEntrega::query()
                    ->with(['requester:id,name', 'department:id,name', 'deliveredBy:id,name'])
                    ->latest('requested_at')
                    ->limit(8)
                    ->get(),
                'movements' => PanolMovimiento::query()
                    ->with(['insumo:id,name', 'responsibleUser:id,name'])
                    ->latest('moved_at')
                    ->limit(8)
                    ->get(),
            ],
        ];
    }

    private function refreshAlerts(Carbon $today): array
    {
        $alerts = [
            'pending_tasks' => CentroApuntesSolicitud::query()->where('status', 'pendiente')->count(),
            'urgent_tasks' => CentroApuntesSolicitud::query()->where('is_urgent', true)->whereNotIn('status', ['entregada', 'rechazada', 'anulada'])->count(),
            'immediate_deliveries' => CentroApuntesSolicitud::query()->where('is_immediate', true)->whereNotIn('status', ['entregada', 'rechazada', 'anulada'])->count(),
            'overdue_tasks' => CentroApuntesSolicitud::query()->whereDate('delivery_date', '<', $today)->whereNotIn('status', ['entregada', 'rechazada', 'anulada'])->count(),
            'critical_stock' => PanolInsumo::query()->where('active', true)->where('current_stock', '>', 0)->whereColumn('current_stock', '<=', 'minimum_stock')->count(),
            'out_of_stock' => PanolInsumo::query()->where('active', true)->where('current_stock', '<=', 0)->count(),
            'machines_in_maintenance' => DB::table('centro_apuntes_maquinas')->where('status', 'en_mantencion')->count(),
            'ready_for_pickup' => CentroApuntesSolicitud::query()->where('status', 'lista_para_retiro')->count(),
            'supplies_expiring' => PanolInsumo::query()->whereNotNull('expires_at')->whereBetween('expires_at', [$today, $today->copy()->addDays(15)])->count(),
        ];

        CentroApuntesAlerta::query()->delete();

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
            if ($value <= 0) {
                continue;
            }

            CentroApuntesAlerta::query()->create([
                'alert_type' => $key,
                'alert_level' => $definitions[$key][0] ?? 'info',
                'title' => $definitions[$key][1] ?? 'Alerta',
                'message' => sprintf('%s: %s caso(s) detectado(s).', $definitions[$key][1] ?? 'Alerta', $value),
                'status' => 'pendiente',
                'detected_at' => Carbon::now(),
                'metadata' => ['count' => $value],
            ]);
        }

        return $alerts;
    }

    private function requestsByDay(Carbon $start, Carbon $end): array
    {
        $items = CentroApuntesSolicitud::query()
            ->selectRaw("DATE(requested_at) as label, count(*) as total")
            ->whereBetween('requested_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->groupBy('label')
            ->orderBy('label')
            ->get();

        return [
            'labels' => $items->pluck('label')->all(),
            'series' => $items->pluck('total')->map(fn ($value) => (int) $value)->all(),
        ];
    }

    private function groupRequestTotalsByMonth(Carbon $from): array
    {
        $items = CentroApuntesSolicitud::query()
            ->selectRaw("DATE_FORMAT(requested_at, '%Y-%m') as label, sum(estimated_total_impressions) as total")
            ->whereDate('requested_at', '>=', $from)
            ->groupBy('label')
            ->orderBy('label')
            ->get();

        return [
            'labels' => $items->pluck('label')->all(),
            'series' => $items->pluck('total')->map(fn ($value) => (int) $value)->all(),
        ];
    }

    private function copiesByMachine(): array
    {
        return CentroApuntesSolicitud::query()
            ->selectRaw('machine_name_snapshot as label, sum(copies_count) as total')
            ->groupBy('machine_name_snapshot')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function requestsBySubject(): array
    {
        return CentroApuntesSolicitud::query()
            ->selectRaw('subject_name_snapshot as label, count(*) as total')
            ->groupBy('subject_name_snapshot')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function requestsByUser(): array
    {
        return CentroApuntesSolicitud::query()
            ->selectRaw('requested_by_name_snapshot as label, count(*) as total')
            ->groupBy('requested_by_name_snapshot')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->toArray();
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
            ->limit(10)
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
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function deliveriesByArea(Carbon $from): array
    {
        return PanolEntrega::query()
            ->selectRaw('coalesce(department_name_snapshot, "Sin área") as label, count(*) as total')
            ->where('status', 'entregada')
            ->whereDate('delivered_at', '>=', $from)
            ->groupBy('department_name_snapshot')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->toArray();
    }
}
