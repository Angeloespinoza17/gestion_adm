<?php

namespace App\Services\Informatica;

use App\Models\It\ItEquipment;
use App\Models\It\ItEquipmentLoan;
use App\Models\It\ItEquipmentMaintenanceReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InformaticaDashboardService
{
    public function __construct(
        private readonly ItEquipmentLoanService $loanService,
    ) {
    }

    public function build(): array
    {
        $this->loanService->refreshOverdueStatuses();

        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $yearStart = $today->copy()->startOfYear();

        return [
            'metrics' => [
                'total_equipment' => ItEquipment::query()->count(),
                'available_equipment' => ItEquipment::query()->available()->count(),
                'loaned_equipment' => ItEquipment::query()->loaned()->count(),
                'maintenance_equipment' => ItEquipment::query()->underMaintenance()->count(),
                'decommissioned_equipment' => ItEquipment::query()->decommissioned()->count(),
                'active_loans' => ItEquipmentLoan::query()->active()->count(),
                'overdue_loans' => ItEquipmentLoan::query()->overdue()->count(),
                'pending_maintenance' => ItEquipmentMaintenanceReport::query()->pending()->count(),
                'maintenance_this_month' => ItEquipmentMaintenanceReport::query()->closed()->whereDate('closed_at', '>=', $monthStart)->count(),
                'damaged_equipment' => ItEquipment::query()->damaged()->count(),
            ],
            'charts' => [
                'equipment_by_status' => ItEquipment::query()
                    ->selectRaw('status as label, count(*) as total')
                    ->groupBy('status')
                    ->orderBy('status')
                    ->get(),
                'equipment_by_type' => ItEquipment::query()
                    ->selectRaw('equipment_type as label, count(*) as total')
                    ->groupBy('equipment_type')
                    ->orderByDesc('total')
                    ->limit(10)
                    ->get(),
                'loans_by_month' => $this->groupByMonth(ItEquipmentLoan::query(), 'borrowed_at', $yearStart),
                'maintenance_by_month' => $this->maintenanceByMonth($yearStart),
                'top_loaned_equipment' => ItEquipmentLoan::query()
                    ->join('it_equipment', 'it_equipment.id', '=', 'it_equipment_loans.it_equipment_id')
                    ->selectRaw('it_equipment.internal_code as label, count(*) as total')
                    ->groupBy('it_equipment_loans.it_equipment_id', 'it_equipment.internal_code')
                    ->orderByDesc('total')
                    ->limit(10)
                    ->get(),
                'top_maintenance_equipment' => ItEquipmentMaintenanceReport::query()
                    ->join('it_equipment', 'it_equipment.id', '=', 'it_equipment_maintenance_reports.it_equipment_id')
                    ->selectRaw('it_equipment.internal_code as label, count(*) as total')
                    ->groupBy('it_equipment_maintenance_reports.it_equipment_id', 'it_equipment.internal_code')
                    ->orderByDesc('total')
                    ->limit(10)
                    ->get(),
            ],
            'recent' => [
                'loans' => ItEquipmentLoan::query()
                    ->with(['equipment:id,internal_code,brand,model', 'deliveredBy:id,name', 'receivedBy:id,name'])
                    ->latest('borrowed_at')
                    ->limit(8)
                    ->get(),
                'maintenance' => ItEquipmentMaintenanceReport::query()
                    ->with(['equipment:id,internal_code,brand,model,status', 'technician:id,name', 'closedBy:id,name'])
                    ->latest('maintenance_date')
                    ->limit(8)
                    ->get(),
            ],
        ];
    }

    private function groupByMonth($query, string $column, Carbon $from): array
    {
        $monthExpression = $this->monthExpression($column);
        $items = $query
            ->selectRaw("{$monthExpression} as label, count(*) as total")
            ->whereDate($column, '>=', $from)
            ->groupBy('label')
            ->orderBy('label')
            ->get();

        return [
            'labels' => $items->pluck('label')->all(),
            'series' => $items->pluck('total')->map(fn ($value) => (int) $value)->all(),
        ];
    }

    private function maintenanceByMonth(Carbon $from): array
    {
        $monthExpression = $this->monthExpression('maintenance_date');
        $items = ItEquipmentMaintenanceReport::query()
            ->selectRaw("{$monthExpression} as label, count(*) as total")
            ->whereDate('maintenance_date', '>=', $from)
            ->groupBy('label')
            ->orderBy('label')
            ->get();

        return [
            'labels' => $items->pluck('label')->all(),
            'series' => $items->pluck('total')->map(fn ($value) => (int) $value)->all(),
        ];
    }

    private function monthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            'sqlsrv' => "format({$column}, 'yyyy-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }
}
