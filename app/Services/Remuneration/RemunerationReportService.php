<?php

namespace App\Services\Remuneration;

use App\Models\Remuneration\RemunerationEmployeeProfile;
use App\Models\Remuneration\RemunerationBookImport;
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

        $payrolls = (clone $query)->with(['lines', 'period'])->get();

        return [
            'period' => $period,
            'metrics' => [
                'payrolls' => $payrolls->count(),
                'gross_total' => (int) $payrolls->sum('gross_total'),
                'gross_taxable_amount' => (int) $payrolls->sum('gross_taxable_amount'),
                'gross_non_taxable_amount' => (int) $payrolls->sum('gross_non_taxable_amount'),
                'net_total' => (int) $payrolls->sum('net_amount'),
                'legal_deductions' => (int) $payrolls->sum('legal_deductions'),
                'other_deductions' => (int) $payrolls->sum('other_deductions'),
                'total_deductions' => (int) $payrolls->sum('total_deductions'),
                'employer_contributions' => (int) $payrolls->sum('employer_contributions'),
                'total_cost' => (int) $payrolls->sum('total_cost'),
                'imported_payrolls' => $payrolls->where('source', 'imported')->count(),
            ],
            'statuses' => $payrolls->groupBy('status')->map->count(),
            'alerts' => [
                'missing_profiles' => max(0, Staff::query()->where('active', true)->count() - RemunerationEmployeeProfile::query()->where('is_active', true)->count()),
                'pending_approval' => (clone $query)->where('status', 'calculada')->count(),
                'pending_payment' => (clone $query)->where('status', 'aprobada')->count(),
            ],
            'analytics' => [
                'trend' => $this->trend(),
                'by_type' => $this->byType($payrolls),
                'top_concepts' => $this->topConcepts($payrolls),
                'period_movement' => $period ? $this->periodMovement($period) : null,
                'recent_imports' => RemunerationBookImport::query()
                    ->with(['period:id,name,year,month,status', 'importedBy:id,name'])
                    ->latest('id')
                    ->limit(6)
                    ->get(),
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function trend(): array
    {
        return RemunerationPayroll::query()
            ->with('period:id,name,year,month')
            ->where('status', '!=', 'anulada')
            ->get()
            ->filter(fn (RemunerationPayroll $payroll) => $payroll->period)
            ->groupBy(fn (RemunerationPayroll $payroll) => sprintf('%04d-%02d', $payroll->period->year, $payroll->period->month))
            ->map(function ($items, string $key) {
                /** @var RemunerationPayroll $first */
                $first = $items->first();

                return [
                    'key' => $key,
                    'period' => $first->period?->name,
                    'year' => $first->period?->year,
                    'month' => $first->period?->month,
                    'payrolls' => $items->count(),
                    'gross_total' => (int) $items->sum('gross_total'),
                    'net_total' => (int) $items->sum('net_amount'),
                    'total_deductions' => (int) $items->sum('total_deductions'),
                    'employer_contributions' => (int) $items->sum('employer_contributions'),
                    'total_cost' => (int) $items->sum('total_cost'),
                ];
            })
            ->sortBy('key')
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, RemunerationPayroll>  $payrolls
     * @return array<int, array<string, mixed>>
     */
    private function byType($payrolls): array
    {
        return $payrolls
            ->groupBy(fn (RemunerationPayroll $payroll) => $payroll->snapshot['book_row']['employee_type']
                ?? $payroll->snapshot['contract_setting']['employee_type']
                ?? 'Sin tipo')
            ->map(fn ($items, string $type) => [
                'type' => $type,
                'count' => $items->count(),
                'gross_total' => (int) $items->sum('gross_total'),
                'net_total' => (int) $items->sum('net_amount'),
                'total_deductions' => (int) $items->sum('total_deductions'),
                'employer_contributions' => (int) $items->sum('employer_contributions'),
            ])
            ->sortByDesc('gross_total')
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, RemunerationPayroll>  $payrolls
     * @return array<int, array<string, mixed>>
     */
    private function topConcepts($payrolls): array
    {
        return $payrolls
            ->flatMap->lines
            ->groupBy(fn ($line) => $line->line_type . '|' . $line->code . '|' . $line->name)
            ->map(function ($items) {
                $first = $items->first();

                return [
                    'line_type' => $first->line_type,
                    'code' => $first->code,
                    'name' => $first->name,
                    'amount' => (int) $items->sum('amount'),
                    'count' => $items->count(),
                ];
            })
            ->sortByDesc('amount')
            ->take(12)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function periodMovement(RemunerationPeriod $period): array
    {
        $previous = RemunerationPeriod::query()
            ->where(function ($query) use ($period) {
                $query->where('year', '<', $period->year)
                    ->orWhere(function ($sameYear) use ($period) {
                        $sameYear->where('year', $period->year)->where('month', '<', $period->month);
                    });
            })
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();

        if (!$previous) {
            return [
                'previous_period' => null,
                'new_count' => 0,
                'missing_count' => 0,
                'largest_net_changes' => [],
            ];
        }

        $currentPayrolls = RemunerationPayroll::query()
            ->with('staff:id,full_name,rut')
            ->where('period_id', $period->id)
            ->where('status', '!=', 'anulada')
            ->get()
            ->keyBy('staff_id');

        $previousPayrolls = RemunerationPayroll::query()
            ->with('staff:id,full_name,rut')
            ->where('period_id', $previous->id)
            ->where('status', '!=', 'anulada')
            ->get()
            ->keyBy('staff_id');

        $currentIds = $currentPayrolls->keys();
        $previousIds = $previousPayrolls->keys();

        $changes = $currentIds
            ->intersect($previousIds)
            ->map(function ($staffId) use ($currentPayrolls, $previousPayrolls) {
                $current = $currentPayrolls->get($staffId);
                $previous = $previousPayrolls->get($staffId);

                return [
                    'staff_id' => $staffId,
                    'staff' => $current?->staff?->full_name,
                    'rut' => $current?->staff?->rut,
                    'previous_net' => (int) ($previous?->net_amount ?? 0),
                    'current_net' => (int) ($current?->net_amount ?? 0),
                    'delta_net' => (int) (($current?->net_amount ?? 0) - ($previous?->net_amount ?? 0)),
                ];
            })
            ->sortByDesc(fn (array $row) => abs($row['delta_net']))
            ->take(10)
            ->values()
            ->all();

        return [
            'previous_period' => $previous->only(['id', 'name', 'year', 'month']),
            'new_count' => $currentIds->diff($previousIds)->count(),
            'missing_count' => $previousIds->diff($currentIds)->count(),
            'largest_net_changes' => $changes,
        ];
    }
}
