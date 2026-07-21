<?php

namespace App\Services\Informatica;

use App\Models\It\ItEquipment;
use App\Models\It\ItEquipmentLoan;
use App\Models\It\ItEquipmentMaintenanceReport;
use Carbon\Carbon;

class InformaticaReportService
{
    public function __construct(
        private readonly ItEquipmentLoanService $loanService,
    ) {
    }

    public function generate(array $filters): array
    {
        $this->loanService->refreshOverdueStatuses();
        [$from, $to] = $this->resolveRange($filters);

        $equipment = ItEquipment::query()
            ->when(!empty($filters['it_equipment_id']), fn ($query) => $query->whereKey($filters['it_equipment_id']))
            ->when(!empty($filters['equipment_type']), fn ($query) => $query->where('equipment_type', $filters['equipment_type']))
            ->when(!empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->get();

        $loans = ItEquipmentLoan::query()
            ->with(['equipment:id,internal_code,equipment_type,brand,model,status'])
            ->whereBetween('borrowed_at', [$from, $to])
            ->when(!empty($filters['it_equipment_id']), fn ($query) => $query->where('it_equipment_id', $filters['it_equipment_id']))
            ->get();

        $maintenance = ItEquipmentMaintenanceReport::query()
            ->with(['equipment:id,internal_code,equipment_type,brand,model,status', 'technician:id,name'])
            ->whereBetween('maintenance_date', [$from, $to])
            ->when(!empty($filters['it_equipment_id']), fn ($query) => $query->where('it_equipment_id', $filters['it_equipment_id']))
            ->get();

        return [
            'summary' => [
                'from' => $from->toDateTimeString(),
                'to' => $to->toDateTimeString(),
                'total_equipment' => $equipment->count(),
                'active_loans' => ItEquipmentLoan::query()->active()->count(),
                'overdue_loans' => ItEquipmentLoan::query()->overdue()->count(),
                'closed_maintenance' => ItEquipmentMaintenanceReport::query()->closed()->count(),
                'pending_maintenance' => ItEquipmentMaintenanceReport::query()->pending()->count(),
            ],
            'sections' => [
                'equipment_by_status' => ItEquipment::query()
                    ->selectRaw('status as label, count(*) as total')
                    ->groupBy('status')
                    ->orderBy('status')
                    ->get(),
                'equipment_by_type' => ItEquipment::query()
                    ->selectRaw('equipment_type as label, count(*) as total')
                    ->groupBy('equipment_type')
                    ->orderByDesc('total')
                    ->get(),
                'loans_by_equipment' => $loans
                    ->groupBy(fn (ItEquipmentLoan $loan) => $loan->equipment?->internal_code ?: 'Sin código')
                    ->map->count()
                    ->sortDesc()
                    ->map(fn ($total, $label) => ['label' => $label, 'total' => $total])
                    ->values(),
                'maintenance_by_equipment' => $maintenance
                    ->groupBy(fn (ItEquipmentMaintenanceReport $report) => $report->equipment?->internal_code ?: 'Sin código')
                    ->map->count()
                    ->sortDesc()
                    ->map(fn ($total, $label) => ['label' => $label, 'total' => $total])
                    ->values(),
                'maintenance_by_month' => $maintenance
                    ->groupBy(fn (ItEquipmentMaintenanceReport $report) => optional($report->maintenance_date)->format('Y-m'))
                    ->map(fn ($items, $label) => [
                        'label' => $label ?: 'Sin fecha',
                        'total' => $items->count(),
                    ])
                    ->values(),
                'top_maintenance_equipment' => $maintenance
                    ->groupBy(fn (ItEquipmentMaintenanceReport $report) => $report->equipment?->internal_code ?: 'Sin código')
                    ->map->count()
                    ->sortDesc()
                    ->take(10)
                    ->map(fn ($total, $label) => ['label' => $label, 'total' => $total])
                    ->values(),
                'top_loaned_equipment' => $loans
                    ->groupBy(fn (ItEquipmentLoan $loan) => $loan->equipment?->internal_code ?: 'Sin código')
                    ->map->count()
                    ->sortDesc()
                    ->take(10)
                    ->map(fn ($total, $label) => ['label' => $label, 'total' => $total])
                    ->values(),
                'decommissioned_equipment' => ItEquipment::query()
                    ->where('status', 'dado_de_baja')
                    ->orderBy('internal_code')
                    ->get(['id', 'internal_code', 'equipment_type', 'brand', 'model', 'location_name', 'responsible_name', 'updated_at']),
            ],
            'detail' => [
                'active_loans' => ItEquipmentLoan::query()
                    ->active()
                    ->with(['equipment:id,internal_code,brand,model,status', 'deliveredBy:id,name'])
                    ->latest('borrowed_at')
                    ->get(),
                'overdue_loans' => ItEquipmentLoan::query()
                    ->overdue()
                    ->with(['equipment:id,internal_code,brand,model,status', 'deliveredBy:id,name'])
                    ->latest('due_at')
                    ->get(),
                'loan_history' => $loans,
                'maintenance_history' => $maintenance,
            ],
        ];
    }

    /**
     * @return array{0:Carbon,1:Carbon}
     */
    private function resolveRange(array $filters): array
    {
        $period = $filters['period'] ?? 'monthly';
        $from = !empty($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : null;
        $to = !empty($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : null;

        if ($from && $to) {
            return [$from, $to];
        }

        $today = Carbon::today();

        return match ($period) {
            'daily' => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            'weekly' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            'semestral' => [$today->copy()->subMonths(6)->startOfDay(), $today->copy()->endOfDay()],
            'annual' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
        };
    }
}
