<?php

namespace App\Services\Remuneration;

use App\Models\Remuneration\RemunerationEmployeeProfile;
use App\Models\Remuneration\RemunerationPayroll;
use App\Models\Remuneration\RemunerationPeriod;
use App\Models\Staff;

class RemunerationReportService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(?int $year = null, ?int $month = null): array
    {
        $period = $this->period($year, $month);
        $query = RemunerationPayroll::query();
        if ($period) {
            $query->where('period_id', $period->id);
        }

        $payrolls = (clone $query)->get();

        return [
            'period' => $period,
            'metrics' => [
                'payrolls' => $payrolls->count(),
                'gross_total' => (int) $payrolls->sum('gross_total'),
                'net_total' => (int) $payrolls->sum('net_amount'),
                'employer_contributions' => (int) $payrolls->sum('employer_contributions'),
                'total_cost' => (int) $payrolls->sum('total_cost'),
            ],
            'statuses' => $payrolls->groupBy('status')->map->count(),
            'alerts' => [
                'missing_profiles' => max(0, Staff::query()->where('active', true)->count() - RemunerationEmployeeProfile::query()->where('is_active', true)->count()),
                'pending_approval' => (clone $query)->where('status', 'calculada')->count(),
                'pending_payment' => (clone $query)->where('status', 'aprobada')->count(),
            ],
            'recent' => RemunerationPayroll::query()
                ->with(['period', 'staff:id,full_name,rut'])
                ->latest('updated_at')
                ->limit(10)
                ->get(),
        ];
    }

    public function exportCsv(?int $periodId = null): string
    {
        $query = RemunerationPayroll::query()->with(['period', 'staff']);
        if ($periodId) {
            $query->where('period_id', $periodId);
        }

        $rows = [
            ['periodo', 'codigo', 'funcionario', 'estado', 'haberes', 'descuentos', 'liquido', 'costo_total'],
        ];

        foreach ($query->orderByDesc('id')->get() as $payroll) {
            $rows[] = [
                optional($payroll->period)->name,
                $payroll->code,
                optional($payroll->staff)->full_name,
                $payroll->status,
                $payroll->gross_total,
                $payroll->total_deductions,
                $payroll->net_amount,
                $payroll->total_cost,
            ];
        }

        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);

        return (string) stream_get_contents($handle);
    }

    private function period(?int $year, ?int $month): ?RemunerationPeriod
    {
        $query = RemunerationPeriod::query()->orderByDesc('year')->orderByDesc('month');
        if ($year) {
            $query->where('year', $year);
        }
        if ($month) {
            $query->where('month', $month);
        }

        return $query->first();
    }
}
