<?php

namespace App\Services\Remuneration;

use App\Models\Remuneration\RemunerationBookAlertRule;
use App\Models\Remuneration\RemunerationBookConceptSetting;
use App\Models\Remuneration\RemunerationBookImport;
use App\Models\Remuneration\RemunerationBookImportRow;
use App\Models\Remuneration\RemunerationPeriod;
use Illuminate\Support\Collection;

class RemunerationBookAnalyticsService
{
    private const EMPLOYER_CONTRIBUTION_CODES = [
        '9976',
        '9977',
        '9995',
        '9997',
        '9998',
        '9999',
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function dashboard(array $filters = []): array
    {
        $imports = $this->imports($filters);
        $currentImport = $imports->sortBy(fn (RemunerationBookImport $import) => $this->periodKey($import) . '-' . str_pad((string) $import->id, 10, '0', STR_PAD_LEFT))->last();
        $previousImport = $currentImport ? $this->previousImport($currentImport) : null;
        $employeeType = $filters['employee_type'] ?? null;

        $currentRows = $this->filteredRows($currentImport?->rows ?? collect(), $employeeType);
        $previousRows = $this->filteredRows($previousImport?->rows ?? collect(), $employeeType);
        $historicalRows = $imports->flatMap(fn (RemunerationBookImport $import) => $this->filteredRows($import->rows, $employeeType));
        $conceptSettings = $this->conceptSettings();

        return [
            'generated_at' => now()->format('Y-m-d H:i'),
            'filters' => [
                'period_id' => $filters['period_id'] ?? null,
                'from_period_id' => $filters['from_period_id'] ?? null,
                'to_period_id' => $filters['to_period_id'] ?? null,
                'import_id' => $filters['import_id'] ?? null,
                'employee_type' => $employeeType,
                'concept_key' => $filters['concept_key'] ?? null,
            ],
            'available_filters' => $this->availableFilters(),
            'current_import' => $this->importPayload($currentImport),
            'previous_import' => $this->importPayload($previousImport),
            'metrics' => $this->metrics($currentRows, $previousRows),
            'trend' => $this->trend($imports, $employeeType),
            'composition' => [
                'earnings' => $this->conceptSummary($currentRows, 'raw_earnings_columns', 'Haber', (int) $currentRows->sum('gross_total')),
                'deductions' => $this->conceptSummary($currentRows, 'raw_deductions_columns', 'Descuento', (int) $currentRows->sum('total_deductions'), false),
                'employer_contributions' => $this->conceptSummary($currentRows, 'raw_deductions_columns', 'Aporte empleador', (int) $currentRows->sum('employer_contributions'), true),
                'waterfall' => $this->waterfall($currentRows),
            ],
            'concept_catalog' => $this->conceptCatalog($historicalRows, $currentRows, $conceptSettings),
            'concept_drilldown' => $this->conceptDrilldown($imports, $employeeType, $filters['concept_key'] ?? null),
            'union_earnings' => $this->unionEarnings($imports, $employeeType, $currentRows, $conceptSettings),
            'staffing' => $this->staffing($currentRows),
            'variations' => $this->variations($currentRows, $previousRows),
            'alerts' => $this->alerts($currentRows),
            'detail_rows' => $this->detailRows($currentRows),
            'coverage' => [
                'imports' => $imports->count(),
                'rows' => $historicalRows->count(),
                'periods' => $imports->pluck(fn (RemunerationBookImport $import) => $this->periodKey($import))->unique()->count(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, RemunerationBookImport>
     */
    private function imports(array $filters): Collection
    {
        $query = RemunerationBookImport::query()
            ->with(['period:id,name,year,month,status', 'rows'])
            ->where('status', 'imported');

        if (!empty($filters['import_id'])) {
            $query->whereKey((int) $filters['import_id']);
        }

        if (!empty($filters['period_id'])) {
            $query->where('period_id', (int) $filters['period_id']);
        }

        if (!empty($filters['from_period_id']) || !empty($filters['to_period_id'])) {
            $from = !empty($filters['from_period_id']) ? RemunerationPeriod::query()->find((int) $filters['from_period_id']) : null;
            $to = !empty($filters['to_period_id']) ? RemunerationPeriod::query()->find((int) $filters['to_period_id']) : null;

            if ($from) {
                $query->where(function ($builder) use ($from) {
                    $builder->where('year', '>', $from->year)
                        ->orWhere(function ($sameYear) use ($from) {
                            $sameYear->where('year', $from->year)->where('month', '>=', $from->month);
                        });
                });
            }

            if ($to) {
                $query->where(function ($builder) use ($to) {
                    $builder->where('year', '<', $to->year)
                        ->orWhere(function ($sameYear) use ($to) {
                            $sameYear->where('year', $to->year)->where('month', '<=', $to->month);
                        });
                });
            }
        }

        return $query
            ->orderBy('year')
            ->orderBy('month')
            ->orderBy('id')
            ->get();
    }

    private function previousImport(RemunerationBookImport $current): ?RemunerationBookImport
    {
        return RemunerationBookImport::query()
            ->with(['period:id,name,year,month,status', 'rows'])
            ->where('status', 'imported')
            ->where(function ($query) use ($current) {
                $query->where('year', '<', $current->year)
                    ->orWhere(function ($sameYear) use ($current) {
                        $sameYear->where('year', $current->year)->where('month', '<', $current->month);
                    });
            })
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function availableFilters(): array
    {
        $imports = RemunerationBookImport::query()
            ->with('period:id,name,year,month,status')
            ->where('status', 'imported')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('id')
            ->get(['id', 'period_id', 'original_filename', 'year', 'month', 'row_count']);

        $types = RemunerationBookImportRow::query()
            ->whereHas('import', fn ($query) => $query->where('status', 'imported'))
            ->whereNotNull('employee_type')
            ->distinct()
            ->orderBy('employee_type')
            ->pluck('employee_type')
            ->values();

        return [
            'imports' => $imports->map(fn (RemunerationBookImport $import) => [
                'id' => $import->id,
                'label' => trim(($import->period?->name ?: sprintf('%04d-%02d', $import->year, $import->month)) . ' - ' . $import->original_filename),
                'period_id' => $import->period_id,
                'row_count' => $import->row_count,
            ])->values(),
            'periods' => RemunerationPeriod::query()
                ->whereIn('id', $imports->pluck('period_id')->filter()->unique())
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->get(['id', 'name', 'year', 'month']),
            'employee_types' => $types,
        ];
    }

    /**
     * @param  Collection<int, RemunerationBookImportRow>  $rows
     * @return Collection<int, RemunerationBookImportRow>
     */
    private function filteredRows(Collection $rows, ?string $employeeType): Collection
    {
        if (!$employeeType) {
            return $rows->values();
        }

        return $rows->where('employee_type', $employeeType)->values();
    }

    /**
     * @param  Collection<int, RemunerationBookImportRow>  $rows
     * @param  Collection<int, RemunerationBookImportRow>  $previousRows
     * @return array<string, mixed>
     */
    private function metrics(Collection $rows, Collection $previousRows): array
    {
        $paidRows = $rows->filter(fn (RemunerationBookImportRow $row) => (int) $row->gross_total > 0);
        $nets = $paidRows->pluck('net_amount')->map(fn ($value) => (int) $value)->sort()->values();
        $grossTotal = (int) $rows->sum('gross_total');
        $deductions = (int) $rows->sum('total_deductions');
        $previousGross = (int) $previousRows->sum('gross_total');
        $previousNet = (int) $previousRows->sum('net_amount');
        $previousDeductions = (int) $previousRows->sum('total_deductions');

        return [
            'workers' => $rows->pluck('rut')->filter()->unique()->count(),
            'paid_workers' => $paidRows->pluck('rut')->filter()->unique()->count(),
            'unpaid_workers' => max(0, $rows->pluck('rut')->filter()->unique()->count() - $paidRows->pluck('rut')->filter()->unique()->count()),
            'gross_total' => $grossTotal,
            'gross_taxable_amount' => (int) $rows->sum('gross_taxable_amount'),
            'gross_non_taxable_amount' => (int) $rows->sum('gross_non_taxable_amount'),
            'legal_deductions' => (int) $rows->sum('legal_deductions'),
            'other_deductions' => (int) $rows->sum('other_deductions'),
            'total_deductions' => $deductions,
            'employer_contributions' => (int) $rows->sum('employer_contributions'),
            'net_total' => (int) $rows->sum('net_amount'),
            'average_net_paid' => $nets->count() ? (int) round($nets->avg()) : 0,
            'median_net_paid' => $this->percentile($nets, 50),
            'discount_rate' => $grossTotal > 0 ? round(($deductions / $grossTotal) * 100, 2) : 0,
            'average_worked_days' => $rows->count() ? round((float) $rows->avg('worked_days'), 2) : 0,
            'average_weekly_hours' => $rows->count() ? round((float) $rows->avg('weekly_hours'), 2) : 0,
            'estimated_fte_44h' => round(((float) $rows->sum('weekly_hours')) / 44, 2),
            'gross_variation' => $this->variation($grossTotal, $previousGross),
            'net_variation' => $this->variation((int) $rows->sum('net_amount'), $previousNet),
            'deduction_variation' => $this->variation($deductions, $previousDeductions),
        ];
    }

    /**
     * @param  Collection<int, RemunerationBookImport>  $imports
     * @return array<int, array<string, mixed>>
     */
    private function trend(Collection $imports, ?string $employeeType): array
    {
        return $imports
            ->map(function (RemunerationBookImport $import) use ($employeeType) {
                $rows = $this->filteredRows($import->rows, $employeeType);
                $paidWorkers = $rows->filter(fn (RemunerationBookImportRow $row) => (int) $row->gross_total > 0)->pluck('rut')->unique()->count();

                return [
                    'key' => $this->periodKey($import),
                    'period' => $import->period?->name ?: sprintf('%04d-%02d', $import->year, $import->month),
                    'year' => $import->year,
                    'month' => $import->month,
                    'workers' => $rows->pluck('rut')->filter()->unique()->count(),
                    'paid_workers' => $paidWorkers,
                    'gross_total' => (int) $rows->sum('gross_total'),
                    'gross_taxable_amount' => (int) $rows->sum('gross_taxable_amount'),
                    'gross_non_taxable_amount' => (int) $rows->sum('gross_non_taxable_amount'),
                    'legal_deductions' => (int) $rows->sum('legal_deductions'),
                    'other_deductions' => (int) $rows->sum('other_deductions'),
                    'total_deductions' => (int) $rows->sum('total_deductions'),
                    'net_total' => (int) $rows->sum('net_amount'),
                    'average_net_paid' => $paidWorkers ? (int) round($rows->where('gross_total', '>', 0)->avg('net_amount')) : 0,
                    'estimated_fte_44h' => round(((float) $rows->sum('weekly_hours')) / 44, 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, RemunerationBookImportRow>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function conceptSummary(Collection $rows, string $field, string $nature, int $baseAmount, ?bool $employerOnly = null): array
    {
        return $rows
            ->flatMap(function (RemunerationBookImportRow $row) use ($field, $nature, $employerOnly) {
                return collect($row->{$field} ?? [])
                    ->filter(function (array $column) use ($employerOnly) {
                        if (empty($column['is_concept']) || !empty($column['is_summary'])) {
                            return false;
                        }
                        $code = (string) ($column['concept_code'] ?? '');
                        $isEmployer = in_array($code, self::EMPLOYER_CONTRIBUTION_CODES, true);
                        if ($employerOnly === true && !$isEmployer) {
                            return false;
                        }
                        if ($employerOnly === false && $isEmployer) {
                            return false;
                        }

                        return $this->moneyValue($column['value'] ?? null) !== 0;
                    })
                    ->map(function (array $column) use ($row, $nature) {
                        $code = (string) ($column['concept_code'] ?? '');
                        $name = $column['concept_name'] ?: $column['header_display'] ?: $column['header'] ?: $code;

                        return [
                            'rut' => $row->rut,
                            'code' => $code,
                            'name' => trim((string) $name),
                            'nature' => $nature,
                            'group' => $nature === 'Descuento' ? $this->deductionGroup($code, (string) $name) : $nature,
                            'amount' => $this->moneyValue($column['value'] ?? null),
                        ];
                    });
            })
            ->groupBy(fn (array $item) => $item['nature'] . '|' . $item['code'] . '|' . $item['name'])
            ->map(function (Collection $items) use ($baseAmount) {
                $first = $items->first();
                $amount = (int) $items->sum('amount');
                $workers = $items->pluck('rut')->unique()->count();

                return [
                    'code' => $first['code'],
                    'name' => $first['name'],
                    'nature' => $first['nature'],
                    'group' => $first['group'],
                    'amount' => $amount,
                    'workers' => $workers,
                    'average_amount' => $workers ? (int) round($amount / $workers) : 0,
                    'share' => $baseAmount > 0 ? round(($amount / $baseAmount) * 100, 2) : 0,
                ];
            })
            ->sortByDesc(fn (array $item) => abs($item['amount']))
            ->take(15)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, RemunerationBookImportRow>  $rows
     * @return array<int, array<string, mixed>>
     */
    /**
     * @return Collection<string, RemunerationBookConceptSetting>
     */
    private function conceptSettings(): Collection
    {
        return RemunerationBookConceptSetting::query()
            ->get()
            ->keyBy('concept_key');
    }

    /**
     * @param  Collection<int, RemunerationBookImportRow>  $rows
     * @param  Collection<int, RemunerationBookImportRow>  $currentRows
     * @param  Collection<string, RemunerationBookConceptSetting>  $settings
     * @return array<int, array<string, mixed>>
     */
    private function conceptCatalog(Collection $rows, Collection $currentRows, Collection $settings): array
    {
        $currentEntries = $this->conceptEntries($currentRows)->groupBy('key');
        $currentBaseAmounts = [
            'Haber' => $this->conceptBaseAmount($currentRows, 'Haber'),
            'Descuento' => $this->conceptBaseAmount($currentRows, 'Descuento'),
            'Aporte empleador' => $this->conceptBaseAmount($currentRows, 'Aporte empleador'),
        ];

        return $this->conceptEntries($rows)
            ->groupBy('key')
            ->map(function (Collection $items) use ($rows, $settings, $currentEntries, $currentBaseAmounts) {
                $first = $items->first();
                $amount = (int) $items->sum('amount');
                $workers = $items->pluck('rut')->filter()->unique()->count();
                $baseAmount = $this->conceptBaseAmount($rows, $first['nature']);
                $setting = $settings->get($first['key']);
                $monthlyItems = $currentEntries->get($first['key'], collect());
                $monthlyAmount = (int) $monthlyItems->sum('amount');
                $monthlyWorkers = $monthlyItems->pluck('rut')->filter()->unique()->count();
                $monthlyBaseAmount = (int) ($currentBaseAmounts[$first['nature']] ?? 0);

                return [
                    'key' => $first['key'],
                    'code' => $first['code'],
                    'name' => $first['name'],
                    'label' => trim(($first['code'] ? '(' . $first['code'] . ') ' : '') . $first['name']),
                    'nature' => $first['nature'],
                    'group' => $first['group'],
                    'is_union_income' => (bool) ($setting?->is_union_income ?? false),
                    'union_notes' => $setting?->notes,
                    'amount' => $amount,
                    'workers' => $workers,
                    'average_amount' => $workers ? (int) round($amount / $workers) : 0,
                    'share' => $baseAmount > 0 ? round(($amount / $baseAmount) * 100, 2) : 0,
                    'monthly_amount' => $monthlyAmount,
                    'monthly_workers' => $monthlyWorkers,
                    'monthly_average_amount' => $monthlyWorkers ? (int) round($monthlyAmount / $monthlyWorkers) : 0,
                    'monthly_share' => $monthlyBaseAmount > 0 ? round(($monthlyAmount / $monthlyBaseAmount) * 100, 2) : 0,
                    'search_text' => mb_strtolower(trim($first['nature'] . ' ' . $first['group'] . ' ' . $first['code'] . ' ' . $first['name'])),
                ];
            })
            ->sortBy([
                ['nature', 'asc'],
                ['amount', 'desc'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, RemunerationBookImport>  $imports
     * @return array<string, mixed>|null
     */
    private function conceptDrilldown(Collection $imports, ?string $employeeType, ?string $conceptKey): ?array
    {
        if (!$conceptKey) {
            return null;
        }

        $currentImport = $imports->sortBy(fn (RemunerationBookImport $import) => $this->periodKey($import) . '-' . str_pad((string) $import->id, 10, '0', STR_PAD_LEFT))->last();
        $currentRows = $this->filteredRows($currentImport?->rows ?? collect(), $employeeType);
        $currentEntries = $this->conceptEntries($currentRows)->where('key', $conceptKey)->values();
        $allEntries = $imports
            ->flatMap(fn (RemunerationBookImport $import) => $this->conceptEntries($this->filteredRows($import->rows, $employeeType), $import))
            ->where('key', $conceptKey)
            ->values();
        $selected = $currentEntries->first() ?: $allEntries->first();

        if (!$selected) {
            return null;
        }

        $totalAmount = (int) $allEntries->sum('amount');
        $monthlyAmount = (int) $currentEntries->sum('amount');
        $workerAmounts = $allEntries
            ->groupBy(fn (array $entry) => $this->conceptEntryWorkerKey($entry))
            ->map(fn (Collection $items) => (int) $items->sum('amount'))
            ->values();
        $monthlyWorkerAmounts = $currentEntries
            ->groupBy(fn (array $entry) => $this->conceptEntryWorkerKey($entry))
            ->map(fn (Collection $items) => (int) $items->sum('amount'))
            ->values();
        $workers = $workerAmounts->count();
        $monthlyWorkers = $monthlyWorkerAmounts->count();
        $baseAmount = (int) $imports->sum(fn (RemunerationBookImport $import) => $this->conceptBaseAmount($this->filteredRows($import->rows, $employeeType), $selected['nature']));
        $monthlyBaseAmount = $this->conceptBaseAmount($currentRows, $selected['nature']);
        $trend = $imports
            ->map(function (RemunerationBookImport $import) use ($employeeType, $conceptKey) {
                $rows = $this->filteredRows($import->rows, $employeeType);
                $entries = $this->conceptEntries($rows, $import)->where('key', $conceptKey)->values();
                $workers = $entries->pluck('rut')->filter()->unique()->count();
                $amount = (int) $entries->sum('amount');

                return [
                    'key' => $this->periodKey($import),
                    'period' => $import->period?->name ?: sprintf('%04d-%02d', $import->year, $import->month),
                    'amount' => $amount,
                    'workers' => $workers,
                    'average_amount' => $workers ? (int) round($amount / $workers) : 0,
                ];
            })
            ->values();
        $previousTrend = $trend->reverse()->skip(1)->first();
        $currentEntriesByWorker = $currentEntries->groupBy(fn (array $entry) => $this->conceptEntryWorkerKey($entry));

        return [
            'selected' => [
                'key' => $selected['key'],
                'code' => $selected['code'],
                'name' => $selected['name'],
                'label' => trim(($selected['code'] ? '(' . $selected['code'] . ') ' : '') . $selected['name']),
                'nature' => $selected['nature'],
                'group' => $selected['group'],
            ],
            'metrics' => [
                'total_amount' => $totalAmount,
                'monthly_amount' => $monthlyAmount,
                'workers' => $workers,
                'average_amount' => $workers ? (int) round($totalAmount / $workers) : 0,
                'monthly_workers' => $monthlyWorkers,
                'monthly_average_amount' => $monthlyWorkers ? (int) round($monthlyAmount / $monthlyWorkers) : 0,
                'median_amount' => $this->percentile($workerAmounts, 50),
                'monthly_median_amount' => $this->percentile($monthlyWorkerAmounts, 50),
                'share' => $baseAmount > 0 ? round(($totalAmount / $baseAmount) * 100, 2) : 0,
                'monthly_share' => $monthlyBaseAmount > 0 ? round(($monthlyAmount / $monthlyBaseAmount) * 100, 2) : 0,
                'variation' => $this->variation($monthlyAmount, (int) ($previousTrend['amount'] ?? 0)),
            ],
            'trend' => $trend->all(),
            'by_type' => $allEntries
                ->groupBy(fn (array $entry) => $entry['employee_type'] ?: 'Sin tipo')
                ->map(function (Collection $items, string $type) use ($totalAmount) {
                    $workers = $items->pluck('rut')->filter()->unique()->count();
                    $amount = (int) $items->sum('amount');

                    return [
                        'type' => $type,
                        'amount' => $amount,
                        'workers' => $workers,
                        'average_amount' => $workers ? (int) round($amount / $workers) : 0,
                        'share' => $totalAmount > 0 ? round(($amount / $totalAmount) * 100, 2) : 0,
                    ];
                })
                ->sortByDesc('amount')
                ->values()
                ->all(),
            'detail_rows' => $allEntries
                ->groupBy(fn (array $entry) => $this->conceptEntryWorkerKey($entry))
                ->map(function (Collection $items, string $workerKey) use ($currentEntriesByWorker) {
                    $currentItems = $currentEntriesByWorker->get($workerKey, collect());
                    $displayEntry = $currentItems->first() ?: $items
                        ->sortByDesc(fn (array $entry) => (string) ($entry['period_key'] ?? ''))
                        ->first();

                    return [
                        'rut' => $displayEntry['rut'],
                        'employee_name' => $displayEntry['employee_name'],
                        'employee_type' => $displayEntry['employee_type'] ?: 'Sin tipo',
                        'amount' => (int) $items->sum('amount'),
                        'monthly_amount' => (int) $currentItems->sum('amount'),
                        'periods' => $items->pluck('period_key')->filter()->unique()->count(),
                        'worked_days' => $displayEntry['worked_days'],
                        'weekly_hours' => $displayEntry['weekly_hours'],
                        'gross_total' => $displayEntry['gross_total'],
                        'total_deductions' => $displayEntry['total_deductions'],
                        'net_amount' => $displayEntry['net_amount'],
                    ];
                })
                ->sortByDesc('amount')
                ->take(100)
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, RemunerationBookImport>  $imports
     * @param  Collection<int, RemunerationBookImportRow>  $currentRows
     * @param  Collection<string, RemunerationBookConceptSetting>  $settings
     * @return array<string, mixed>
     */
    private function unionEarnings(Collection $imports, ?string $employeeType, Collection $currentRows, Collection $settings): array
    {
        $unionKeys = $settings
            ->filter(fn (RemunerationBookConceptSetting $setting) => $setting->is_union_income && $setting->nature === 'Haber')
            ->keys()
            ->values();

        $empty = [
            'metrics' => [
                'total_amount' => 0,
                'workers' => 0,
                'average_amount' => 0,
                'monthly_average_amount' => 0,
                'concept_count' => $unionKeys->count(),
                'share' => 0,
            ],
            'trend' => [],
            'by_concept' => [],
            'by_type' => [],
            'detail_rows' => [],
        ];

        if ($unionKeys->isEmpty()) {
            return $empty;
        }

        $currentEntries = $this->conceptEntries($currentRows)
            ->whereIn('key', $unionKeys->all())
            ->values();
        $totalAmount = (int) $currentEntries->sum('amount');
        $workers = $currentEntries->pluck('rut')->filter()->unique()->count();
        $baseAmount = $this->conceptBaseAmount($currentRows, 'Haber');
        $trend = $imports
            ->map(function (RemunerationBookImport $import) use ($employeeType, $unionKeys) {
                $rows = $this->filteredRows($import->rows, $employeeType);
                $entries = $this->conceptEntries($rows, $import)
                    ->whereIn('key', $unionKeys->all())
                    ->values();
                $amount = (int) $entries->sum('amount');
                $workers = $entries->pluck('rut')->filter()->unique()->count();

                return [
                    'key' => $this->periodKey($import),
                    'period' => $import->period?->name ?: sprintf('%04d-%02d', $import->year, $import->month),
                    'amount' => $amount,
                    'workers' => $workers,
                    'average_amount' => $workers ? (int) round($amount / $workers) : 0,
                ];
            })
            ->values();

        return [
            'metrics' => [
                'total_amount' => $totalAmount,
                'workers' => $workers,
                'average_amount' => $workers ? (int) round($totalAmount / $workers) : 0,
                'monthly_average_amount' => $trend->count() ? (int) round($trend->avg('amount')) : 0,
                'concept_count' => $unionKeys->count(),
                'share' => $baseAmount > 0 ? round(($totalAmount / $baseAmount) * 100, 2) : 0,
            ],
            'trend' => $trend->all(),
            'by_concept' => $currentEntries
                ->groupBy('key')
                ->map(function (Collection $items) use ($totalAmount, $settings) {
                    $first = $items->first();
                    $amount = (int) $items->sum('amount');
                    $workers = $items->pluck('rut')->filter()->unique()->count();
                    $setting = $settings->get($first['key']);

                    return [
                        'key' => $first['key'],
                        'code' => $first['code'],
                        'name' => $first['name'],
                        'label' => trim(($first['code'] ? '(' . $first['code'] . ') ' : '') . $first['name']),
                        'group' => $first['group'],
                        'notes' => $setting?->notes,
                        'amount' => $amount,
                        'workers' => $workers,
                        'average_amount' => $workers ? (int) round($amount / $workers) : 0,
                        'share' => $totalAmount > 0 ? round(($amount / $totalAmount) * 100, 2) : 0,
                    ];
                })
                ->sortByDesc('amount')
                ->values()
                ->all(),
            'by_type' => $currentEntries
                ->groupBy(fn (array $entry) => $entry['employee_type'] ?: 'Sin tipo')
                ->map(function (Collection $items, string $type) use ($totalAmount) {
                    $amount = (int) $items->sum('amount');
                    $workers = $items->pluck('rut')->filter()->unique()->count();

                    return [
                        'type' => $type,
                        'amount' => $amount,
                        'workers' => $workers,
                        'average_amount' => $workers ? (int) round($amount / $workers) : 0,
                        'share' => $totalAmount > 0 ? round(($amount / $totalAmount) * 100, 2) : 0,
                    ];
                })
                ->sortByDesc('amount')
                ->values()
                ->all(),
            'detail_rows' => $currentEntries
                ->groupBy(fn (array $entry) => $this->normalizeRut($entry['rut']))
                ->map(function (Collection $items) {
                    $first = $items->first();

                    return [
                        'rut' => $first['rut'],
                        'employee_name' => $first['employee_name'],
                        'employee_type' => $first['employee_type'] ?: 'Sin tipo',
                        'amount' => (int) $items->sum('amount'),
                        'concepts' => $items->pluck('label')->filter()->unique()->values()->all(),
                        'concept_count' => $items->pluck('key')->unique()->count(),
                        'worked_days' => $first['worked_days'],
                        'weekly_hours' => $first['weekly_hours'],
                        'gross_total' => $first['gross_total'],
                        'total_deductions' => $first['total_deductions'],
                        'net_amount' => $first['net_amount'],
                    ];
                })
                ->sortByDesc('amount')
                ->take(100)
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, RemunerationBookImportRow>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function conceptEntries(Collection $rows, ?RemunerationBookImport $import = null): Collection
    {
        return $rows
            ->flatMap(function (RemunerationBookImportRow $row) use ($import) {
                $earnings = collect($row->raw_earnings_columns ?? [])
                    ->map(fn (array $column) => $this->conceptEntry($row, $column, 'Haber', $import));
                $deductions = collect($row->raw_deductions_columns ?? [])
                    ->map(function (array $column) use ($row, $import) {
                        $code = (string) ($column['concept_code'] ?? '');
                        $nature = in_array($code, self::EMPLOYER_CONTRIBUTION_CODES, true) ? 'Aporte empleador' : 'Descuento';

                        return $this->conceptEntry($row, $column, $nature, $import);
                    });

                return $earnings->merge($deductions)->filter();
            })
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function conceptEntry(RemunerationBookImportRow $row, array $column, string $nature, ?RemunerationBookImport $import = null): ?array
    {
        if (empty($column['is_concept']) || !empty($column['is_summary'])) {
            return null;
        }

        $amount = abs($this->moneyValue($column['value'] ?? null));
        if ($amount === 0) {
            return null;
        }

        $code = (string) ($column['concept_code'] ?? '');
        $name = trim((string) ($column['concept_name'] ?: $column['header_display'] ?: $column['header'] ?: $code));
        $group = $nature === 'Descuento' ? $this->deductionGroup($code, $name) : $nature;

        return [
            'key' => $this->conceptKey($nature, $code, $name),
            'period_key' => $import ? $this->periodKey($import) : null,
            'period' => $import ? ($import->period?->name ?: sprintf('%04d-%02d', $import->year, $import->month)) : null,
            'rut' => $row->rut,
            'employee_name' => $row->employee_name,
            'employee_type' => $row->employee_type ?: 'Sin tipo',
            'code' => $code,
            'name' => $name,
            'label' => trim(($code ? '(' . $code . ') ' : '') . $name),
            'nature' => $nature,
            'group' => $group,
            'amount' => $amount,
            'worked_days' => (float) $row->worked_days,
            'weekly_hours' => (float) $row->weekly_hours,
            'gross_total' => (int) $row->gross_total,
            'total_deductions' => (int) $row->total_deductions,
            'net_amount' => (int) $row->net_amount,
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function conceptEntryWorkerKey(array $entry): string
    {
        $rut = $this->normalizeRut((string) ($entry['rut'] ?? ''));

        return $rut !== ''
            ? $rut
            : sha1(($entry['employee_name'] ?? '') . '|' . ($entry['employee_type'] ?? ''));
    }

    private function conceptBaseAmount(Collection $rows, string $nature): int
    {
        return match ($nature) {
            'Haber' => abs((int) $rows->sum('gross_total')),
            'Descuento' => abs((int) $rows->sum('total_deductions')),
            'Aporte empleador' => abs((int) $rows->sum('employer_contributions')),
            default => 0,
        };
    }

    private function conceptKey(string $nature, string $code, string $name): string
    {
        return sha1($nature . '|' . $code . '|' . mb_strtolower(trim($name)));
    }

    /**
     * @param  Collection<int, RemunerationBookImportRow>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function waterfall(Collection $rows): array
    {
        return [
            ['label' => 'Total haberes', 'amount' => abs((int) $rows->sum('gross_total')), 'type' => 'positive'],
            ['label' => 'Descuentos legales', 'amount' => abs((int) $rows->sum('legal_deductions')), 'type' => 'deduction'],
            ['label' => 'Otros descuentos', 'amount' => abs((int) $rows->sum('other_deductions')), 'type' => 'deduction'],
            ['label' => 'Total líquido', 'amount' => abs((int) $rows->sum('net_amount')), 'type' => 'total'],
        ];
    }

    /**
     * @param  Collection<int, RemunerationBookImportRow>  $rows
     * @return array<string, mixed>
     */
    private function staffing(Collection $rows): array
    {
        $grossTotal = max(1, (int) $rows->sum('gross_total'));
        $workerTotal = max(1, $rows->pluck('rut')->filter()->unique()->count());
        $paidNets = $rows
            ->filter(fn (RemunerationBookImportRow $row) => (int) $row->gross_total > 0)
            ->pluck('net_amount')
            ->map(fn ($value) => (int) $value)
            ->sort()
            ->values();

        $buckets = [
            ['label' => '< $500 mil', 'min' => null, 'max' => 499999],
            ['label' => '$500-$750 mil', 'min' => 500000, 'max' => 749999],
            ['label' => '$750 mil-$1 MM', 'min' => 750000, 'max' => 999999],
            ['label' => '$1-$1,25 MM', 'min' => 1000000, 'max' => 1249999],
            ['label' => '$1,25-$1,5 MM', 'min' => 1250000, 'max' => 1499999],
            ['label' => '> $1,5 MM', 'min' => 1500000, 'max' => null],
        ];

        return [
            'by_type' => $rows
                ->groupBy(fn (RemunerationBookImportRow $row) => $row->employee_type ?: 'Sin tipo')
                ->map(function (Collection $items, string $type) use ($grossTotal, $workerTotal) {
                    $workers = $items->pluck('rut')->filter()->unique()->count();

                    return [
                        'type' => $type,
                        'workers' => $workers,
                    'paid_workers' => $items->filter(fn (RemunerationBookImportRow $row) => (int) $row->gross_total > 0)->pluck('rut')->filter()->unique()->count(),
                    'gross_total' => (int) $items->sum('gross_total'),
                    'net_total' => (int) $items->sum('net_amount'),
                    'total_deductions' => (int) $items->sum('total_deductions'),
                    'average_net' => $items->filter(fn (RemunerationBookImportRow $row) => (int) $row->gross_total > 0)->count()
                        ? (int) round($items->filter(fn (RemunerationBookImportRow $row) => (int) $row->gross_total > 0)->avg('net_amount'))
                        : 0,
                        'average_weekly_hours' => $items->count() ? round((float) $items->avg('weekly_hours'), 2) : 0,
                        'staff_share' => round(($workers / $workerTotal) * 100, 2),
                        'gross_share' => round(((int) $items->sum('gross_total') / $grossTotal) * 100, 2),
                    ];
                })
                ->sortByDesc('gross_total')
                ->values()
                ->all(),
            'net_distribution' => collect($buckets)
                ->map(fn (array $bucket) => [
                    'label' => $bucket['label'],
                    'workers' => $paidNets->filter(fn (int $net) => ($bucket['min'] === null || $net >= $bucket['min']) && ($bucket['max'] === null || $net <= $bucket['max']))->count(),
                ])
                ->all(),
            'percentiles' => [
                'p10' => $this->percentile($paidNets, 10),
                'p25' => $this->percentile($paidNets, 25),
                'p50' => $this->percentile($paidNets, 50),
                'p75' => $this->percentile($paidNets, 75),
                'p90' => $this->percentile($paidNets, 90),
                'p90_p10_ratio' => $this->percentile($paidNets, 10) > 0
                    ? round($this->percentile($paidNets, 90) / $this->percentile($paidNets, 10), 2)
                    : 0,
            ],
        ];
    }

    /**
     * @param  Collection<int, RemunerationBookImportRow>  $currentRows
     * @param  Collection<int, RemunerationBookImportRow>  $previousRows
     * @return array<string, mixed>
     */
    private function variations(Collection $currentRows, Collection $previousRows): array
    {
        $current = $currentRows->keyBy(fn (RemunerationBookImportRow $row) => $this->normalizeRut($row->rut));
        $previous = $previousRows->keyBy(fn (RemunerationBookImportRow $row) => $this->normalizeRut($row->rut));
        $shared = $current->keys()->intersect($previous->keys());

        $changes = $shared
            ->map(function (string $rut) use ($current, $previous) {
                $currentRow = $current->get($rut);
                $previousRow = $previous->get($rut);
                $previousNet = (int) ($previousRow?->net_amount ?? 0);
                $currentNet = (int) ($currentRow?->net_amount ?? 0);

                return [
                    'rut' => $currentRow?->rut,
                    'employee_name' => $currentRow?->employee_name,
                    'employee_type' => $currentRow?->employee_type,
                    'previous_net' => $previousNet,
                    'current_net' => $currentNet,
                    'delta_net' => $currentNet - $previousNet,
                    'delta_percent' => $previousNet !== 0 ? round((($currentNet - $previousNet) / $previousNet) * 100, 2) : null,
                ];
            })
            ->sortByDesc(fn (array $item) => abs($item['delta_net']))
            ->values();

        return [
            'new_workers' => $current->keys()->diff($previous->keys())->count(),
            'missing_workers' => $previous->keys()->diff($current->keys())->count(),
            'top_increases' => $changes->filter(fn (array $item) => $item['delta_net'] > 0)->take(10)->values()->all(),
            'top_decreases' => $changes->filter(fn (array $item) => $item['delta_net'] < 0)->take(10)->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, RemunerationBookImportRow>  $rows
     * @return array<string, mixed>
     */
    private function alerts(Collection $rows): array
    {
        $alerts = [];
        $definitions = collect($this->systemAlertDefinitions())->keyBy('key');
        $duplicateRuts = $rows
            ->groupBy(fn (RemunerationBookImportRow $row) => $this->normalizeRut($row->rut))
            ->filter(fn (Collection $items, string $rut) => $rut !== '' && $items->count() > 1)
            ->keys()
            ->all();

        foreach ($rows as $row) {
            $deductionRate = (int) $row->gross_total > 0
                ? round(((int) $row->total_deductions / max(1, (int) $row->gross_total)) * 100, 2)
                : 0;

            $this->appendSystemAlert($alerts, $definitions, $row, 'worker_without_pay', (int) $row->gross_total === 0, 'Total haberes igual a cero.', (int) $row->gross_total, 0);
            $this->appendSystemAlert($alerts, $definitions, $row, 'paid_without_days', (float) $row->worked_days === 0.0 && (int) $row->gross_total > 0, 'DT igual a cero con haberes mayores a cero.', (float) $row->worked_days, 0);
            $this->appendSystemAlert($alerts, $definitions, $row, 'exceptional_hours', (float) $row->weekly_hours > 60.0, 'Carga horaria superior al umbral inicial de 60 horas.', (float) $row->weekly_hours, 60);
            $this->appendSystemAlert($alerts, $definitions, $row, 'high_discount', (int) $row->gross_total > 0 && $deductionRate > 50, 'Descuentos superiores al 50% de los haberes.', $deductionRate, 50);
            $this->appendSystemAlert($alerts, $definitions, $row, 'negative_net', (int) $row->net_amount < 0, 'Total líquido menor que cero.', (int) $row->net_amount, 0);
            $this->appendSystemAlert($alerts, $definitions, $row, 'duplicate_rut', in_array($this->normalizeRut($row->rut), $duplicateRuts, true), 'El RUT aparece más de una vez en el libro.');
            $this->appendSystemAlert($alerts, $definitions, $row, 'reconciliation_error', !$this->isReconciled($row), 'Haberes, descuentos o líquido no cuadran.');

            foreach (($row->errors['errors'] ?? []) as $message) {
                $this->appendSystemAlert($alerts, $definitions, $row, 'origin_error', true, (string) $message);
            }
            foreach (($row->errors['warnings'] ?? []) as $message) {
                $this->appendSystemAlert($alerts, $definitions, $row, 'origin_warning', true, (string) $message);
            }

            foreach (['raw_earnings_columns', 'raw_deductions_columns'] as $field) {
                foreach (($row->{$field} ?? []) as $column) {
                    $conceptValue = $this->moneyValue($column['value'] ?? null);
                    $this->appendSystemAlert(
                        $alerts,
                        $definitions,
                        $row,
                        'negative_concept',
                        !empty($column['is_concept']) && $conceptValue < 0,
                        ($column['header_display'] ?? $column['header'] ?? 'Concepto') . ' tiene monto negativo.',
                        $conceptValue,
                        0,
                    );
                }
            }
        }

        $rules = RemunerationBookAlertRule::query()
            ->orderByDesc('enabled')
            ->orderBy('name')
            ->get();
        $this->appendCustomRuleAlerts($alerts, $rows, $rules->filter(fn (RemunerationBookAlertRule $rule) => $rule->enabled)->values());

        $collection = collect($alerts);

        return [
            'total' => $collection->count(),
            'by_severity' => $collection->groupBy('severity')->map->count()->all(),
            'by_type' => $collection->groupBy('type')->map->count()->all(),
            'items' => $collection->take(100)->values()->all(),
            'definitions' => $definitions->values()->all(),
            'rules' => $rules->map(fn (RemunerationBookAlertRule $rule) => $this->alertRulePayload($rule))->values()->all(),
            'active_rule_count' => $rules->filter(fn (RemunerationBookAlertRule $rule) => $rule->enabled)->count(),
            'reconciled_rows' => $rows->filter(fn (RemunerationBookImportRow $row) => $this->isReconciled($row))->count(),
            'reconciliation_rate' => $rows->count() ? round(($rows->filter(fn (RemunerationBookImportRow $row) => $this->isReconciled($row))->count() / $rows->count()) * 100, 2) : 0,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function systemAlertDefinitions(): array
    {
        return [
            [
                'key' => 'worker_without_pay',
                'id' => 'system:worker_without_pay',
                'name' => 'Trabajador sin pago',
                'severity' => 'requiere_revision',
                'metric' => 'gross_total',
                'metric_label' => 'Total haberes',
                'operator' => 'eq',
                'threshold_value' => 0,
                'description' => 'Detecta filas donde el total de haberes es cero. Puede corresponder a trabajador informado sin remuneración o a una fila incompleta.',
                'suggested_review' => 'Revisar si el trabajador debió pagarse en el periodo o si el registro quedó en el libro solo por trazabilidad.',
                'system' => true,
            ],
            [
                'key' => 'paid_without_days',
                'id' => 'system:paid_without_days',
                'name' => 'Pago sin días trabajados',
                'severity' => 'critica',
                'metric' => 'worked_days',
                'metric_label' => 'DT',
                'operator' => 'eq',
                'threshold_value' => 0,
                'description' => 'Detecta pagos con DT igual a cero y haberes mayores a cero.',
                'suggested_review' => 'Validar si corresponde a ajuste, bono excepcional, finiquito o error de días trabajados.',
                'system' => true,
            ],
            [
                'key' => 'exceptional_hours',
                'id' => 'system:exceptional_hours',
                'name' => 'Carga horaria excepcional',
                'severity' => 'requiere_revision',
                'metric' => 'weekly_hours',
                'metric_label' => 'Carga horaria',
                'operator' => 'gt',
                'threshold_value' => 60,
                'description' => 'Detecta cargas horarias superiores al umbral inicial de 60 horas.',
                'suggested_review' => 'Confirmar si existen contratos múltiples, acumulación de horas o una carga mal informada.',
                'system' => true,
            ],
            [
                'key' => 'high_discount',
                'id' => 'system:high_discount',
                'name' => 'Descuento elevado',
                'severity' => 'requiere_revision',
                'metric' => 'deduction_rate',
                'metric_label' => 'Descuentos sobre haberes',
                'operator' => 'gt',
                'threshold_value' => 50,
                'description' => 'Detecta casos donde los descuentos superan el 50% del total de haberes.',
                'suggested_review' => 'Revisar anticipos, créditos, retenciones judiciales u otros descuentos extraordinarios.',
                'system' => true,
            ],
            [
                'key' => 'negative_net',
                'id' => 'system:negative_net',
                'name' => 'Líquido negativo',
                'severity' => 'critica',
                'metric' => 'net_amount',
                'metric_label' => 'Total líquido',
                'operator' => 'lt',
                'threshold_value' => 0,
                'description' => 'Detecta liquidaciones con monto líquido menor que cero.',
                'suggested_review' => 'Revisar descuentos, reversas y conciliación antes de contabilizar o pagar.',
                'system' => true,
            ],
            [
                'key' => 'duplicate_rut',
                'id' => 'system:duplicate_rut',
                'name' => 'RUT duplicado',
                'severity' => 'requiere_revision',
                'metric' => 'rut',
                'metric_label' => 'RUT',
                'operator' => 'duplicado',
                'threshold_value' => null,
                'description' => 'Detecta RUT presentes más de una vez en el mismo libro.',
                'suggested_review' => 'Confirmar si corresponde a más de un contrato o si falta un identificador adicional.',
                'system' => true,
            ],
            [
                'key' => 'reconciliation_error',
                'id' => 'system:reconciliation_error',
                'name' => 'Error de conciliación',
                'severity' => 'critica',
                'metric' => 'reconciliation',
                'metric_label' => 'Conciliación',
                'operator' => 'neq',
                'threshold_value' => null,
                'description' => 'Detecta filas donde haberes, descuentos o líquido no cuadran.',
                'suggested_review' => 'Validar que total haberes sea imponible más no imponible, total descuentos sea legal más otros y líquido sea haberes menos descuentos.',
                'system' => true,
            ],
            [
                'key' => 'origin_error',
                'id' => 'system:origin_error',
                'name' => 'Error de origen',
                'severity' => 'critica',
                'metric' => 'source_validation',
                'metric_label' => 'Validación de origen',
                'operator' => 'detectado',
                'threshold_value' => null,
                'description' => 'Muestra errores detectados durante la lectura o normalización del archivo importado.',
                'suggested_review' => 'Corregir el archivo de origen o revisar el mapeo de columnas.',
                'system' => true,
            ],
            [
                'key' => 'origin_warning',
                'id' => 'system:origin_warning',
                'name' => 'Advertencia de origen',
                'severity' => 'informativa',
                'metric' => 'source_validation',
                'metric_label' => 'Validación de origen',
                'operator' => 'detectado',
                'threshold_value' => null,
                'description' => 'Muestra advertencias detectadas durante la lectura o normalización del archivo importado.',
                'suggested_review' => 'Revisar si la advertencia afecta el cálculo o solo documenta una diferencia menor.',
                'system' => true,
            ],
            [
                'key' => 'negative_concept',
                'id' => 'system:negative_concept',
                'name' => 'Concepto negativo',
                'severity' => 'requiere_revision',
                'metric' => 'concept_amount',
                'metric_label' => 'Monto concepto',
                'operator' => 'lt',
                'threshold_value' => 0,
                'description' => 'Detecta conceptos de haberes o descuentos con monto negativo.',
                'suggested_review' => 'Validar si corresponde a ajuste legítimo, reversa o error de signo.',
                'system' => true,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $alerts
     * @param  Collection<string, array<string, mixed>>  $definitions
     */
    private function appendSystemAlert(
        array &$alerts,
        Collection $definitions,
        RemunerationBookImportRow $row,
        string $key,
        bool $condition,
        string $message,
        int|float|null $metricValue = null,
        int|float|null $thresholdValue = null,
    ): void {
        $definition = $definitions->get($key, []);

        $this->appendAlert(
            $alerts,
            $row,
            (string) ($definition['name'] ?? $key),
            $condition,
            (string) ($definition['severity'] ?? 'requiere_revision'),
            $message,
            (string) ($definition['id'] ?? 'system:' . $key),
            (string) ($definition['description'] ?? ''),
            (string) ($definition['suggested_review'] ?? ''),
            $metricValue,
            $thresholdValue ?? ($definition['threshold_value'] ?? null),
            $definition['metric'] ?? null,
            $definition['metric_label'] ?? null,
            $definition['operator'] ?? null,
            true,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $alerts
     * @param  Collection<int, RemunerationBookImportRow>  $rows
     * @param  Collection<int, RemunerationBookAlertRule>  $rules
     */
    private function appendCustomRuleAlerts(array &$alerts, Collection $rows, Collection $rules): void
    {
        foreach ($rules as $rule) {
            foreach ($rows as $row) {
                if ($rule->employee_type && ($row->employee_type ?: 'Sin tipo') !== $rule->employee_type) {
                    continue;
                }

                $metricValue = $this->customRuleMetricValue($row, $rule);
                if ($metricValue === null || !$this->passesAlertRule($metricValue, $rule->operator, (float) $rule->threshold_value)) {
                    continue;
                }

                $metricLabel = $this->alertMetricLabel($rule->metric, $rule->concept_label);
                $message = trim($rule->name . ': ' . $metricLabel . ' ' . $this->alertOperatorLabel($rule->operator) . ' ' . $this->alertThresholdText($rule) . '.');

                $this->appendAlert(
                    $alerts,
                    $row,
                    $rule->name,
                    true,
                    $rule->severity,
                    $message,
                    'custom:' . $rule->id,
                    $rule->description ?: 'Regla creada manualmente para controlar casos específicos del libro de remuneraciones.',
                    'Revisar el valor detectado contra el criterio configurado y justificar si corresponde a un caso válido.',
                    $metricValue,
                    (float) $rule->threshold_value,
                    $rule->metric,
                    $metricLabel,
                    $rule->operator,
                    false,
                );
            }
        }
    }

    private function customRuleMetricValue(RemunerationBookImportRow $row, RemunerationBookAlertRule $rule): ?float
    {
        return match ($rule->metric) {
            'gross_total' => (float) $row->gross_total,
            'net_amount' => (float) $row->net_amount,
            'total_deductions' => (float) $row->total_deductions,
            'legal_deductions' => (float) $row->legal_deductions,
            'other_deductions' => (float) $row->other_deductions,
            'worked_days' => (float) $row->worked_days,
            'weekly_hours' => (float) $row->weekly_hours,
            'deduction_rate' => (int) $row->gross_total > 0
                ? round(((int) $row->total_deductions / max(1, (int) $row->gross_total)) * 100, 2)
                : 0.0,
            'concept_amount' => $rule->concept_key
                ? (float) $this->conceptEntries(collect([$row]))->where('key', $rule->concept_key)->sum('amount')
                : null,
            default => null,
        };
    }

    private function passesAlertRule(float $value, string $operator, float $threshold): bool
    {
        return match ($operator) {
            'gt' => $value > $threshold,
            'gte' => $value >= $threshold,
            'lt' => $value < $threshold,
            'lte' => $value <= $threshold,
            'eq' => abs($value - $threshold) < 0.0001,
            'neq' => abs($value - $threshold) >= 0.0001,
            default => false,
        };
    }

    private function alertMetricLabel(string $metric, ?string $conceptLabel = null): string
    {
        return match ($metric) {
            'gross_total' => 'Total haberes',
            'net_amount' => 'Total líquido',
            'total_deductions' => 'Total descuentos',
            'legal_deductions' => 'Descuentos legales',
            'other_deductions' => 'Otros descuentos',
            'deduction_rate' => 'Descuentos sobre haberes',
            'worked_days' => 'DT',
            'weekly_hours' => 'Carga horaria',
            'concept_amount' => $conceptLabel ?: 'Monto de concepto',
            default => $metric,
        };
    }

    private function alertOperatorLabel(string $operator): string
    {
        return match ($operator) {
            'gt' => 'mayor que',
            'gte' => 'mayor o igual que',
            'lt' => 'menor que',
            'lte' => 'menor o igual que',
            'eq' => 'igual a',
            'neq' => 'distinto de',
            default => $operator,
        };
    }

    private function alertThresholdText(RemunerationBookAlertRule $rule): string
    {
        $value = (float) $rule->threshold_value;

        return $rule->metric === 'deduction_rate'
            ? rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',') . '%'
            : rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    private function alertRulePayload(RemunerationBookAlertRule $rule): array
    {
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'description' => $rule->description,
            'severity' => $rule->severity,
            'metric' => $rule->metric,
            'metric_label' => $this->alertMetricLabel($rule->metric, $rule->concept_label),
            'operator' => $rule->operator,
            'operator_label' => $this->alertOperatorLabel($rule->operator),
            'threshold_value' => (float) $rule->threshold_value,
            'concept_key' => $rule->concept_key,
            'concept_label' => $rule->concept_label,
            'employee_type' => $rule->employee_type,
            'enabled' => (bool) $rule->enabled,
            'system' => false,
            'created_at' => $rule->created_at?->format('Y-m-d H:i'),
            'updated_at' => $rule->updated_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $alerts
     */
    private function appendAlert(
        array &$alerts,
        RemunerationBookImportRow $row,
        string $type,
        bool $condition,
        string $severity,
        string $message,
        ?string $ruleId = null,
        ?string $explanation = null,
        ?string $suggestedReview = null,
        int|float|null $metricValue = null,
        int|float|null $thresholdValue = null,
        ?string $metric = null,
        ?string $metricLabel = null,
        ?string $operator = null,
        bool $system = true,
    ): void {
        if (!$condition) {
            return;
        }

        $alerts[] = [
            'rule_id' => $ruleId,
            'system' => $system,
            'severity' => $severity,
            'type' => $type,
            'message' => $message,
            'explanation' => $explanation,
            'suggested_review' => $suggestedReview,
            'metric' => $metric,
            'metric_label' => $metricLabel,
            'metric_value' => $metricValue,
            'threshold_value' => $thresholdValue,
            'operator' => $operator,
            'rut' => $row->rut,
            'employee_name' => $row->employee_name,
            'employee_type' => $row->employee_type,
            'gross_total' => (int) $row->gross_total,
            'total_deductions' => (int) $row->total_deductions,
            'net_amount' => (int) $row->net_amount,
        ];
    }

    /**
     * @param  Collection<int, RemunerationBookImportRow>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function detailRows(Collection $rows): array
    {
        return $rows
            ->sortByDesc('gross_total')
            ->take(80)
            ->map(fn (RemunerationBookImportRow $row) => [
                'rut' => $row->rut,
                'employee_name' => $row->employee_name,
                'employee_type' => $row->employee_type ?: 'Sin tipo',
                'worked_days' => (float) $row->worked_days,
                'weekly_hours' => (float) $row->weekly_hours,
                'gross_taxable_amount' => (int) $row->gross_taxable_amount,
                'gross_non_taxable_amount' => (int) $row->gross_non_taxable_amount,
                'gross_total' => (int) $row->gross_total,
                'legal_deductions' => (int) $row->legal_deductions,
                'other_deductions' => (int) $row->other_deductions,
                'total_deductions' => (int) $row->total_deductions,
                'net_amount' => (int) $row->net_amount,
                'alert_count' => count($row->errors['errors'] ?? []) + count($row->errors['warnings'] ?? []),
                'reconciled' => $this->isReconciled($row),
            ])
            ->values()
            ->all();
    }

    private function isReconciled(RemunerationBookImportRow $row): bool
    {
        return (int) $row->gross_total === (int) $row->gross_taxable_amount + (int) $row->gross_non_taxable_amount
            && (int) $row->total_deductions === (int) $row->legal_deductions + (int) $row->other_deductions
            && (int) $row->net_amount === (int) $row->gross_total - (int) $row->total_deductions;
    }

    private function variation(int $current, int $previous): array
    {
        return [
            'absolute' => $current - $previous,
            'percent' => $previous !== 0 ? round((($current - $previous) / $previous) * 100, 2) : null,
        ];
    }

    /**
     * @param  Collection<int, int>  $values
     */
    private function percentile(Collection $values, int $percentile): int
    {
        $sorted = $values->map(fn ($value) => (int) $value)->sort()->values();
        $count = $sorted->count();
        if ($count === 0) {
            return 0;
        }
        if ($count === 1) {
            return (int) $sorted->first();
        }

        $position = ($percentile / 100) * ($count - 1);
        $lower = (int) floor($position);
        $upper = (int) ceil($position);
        if ($lower === $upper) {
            return (int) $sorted[$lower];
        }

        $weight = $position - $lower;

        return (int) round(((int) $sorted[$lower] * (1 - $weight)) + ((int) $sorted[$upper] * $weight));
    }

    private function moneyValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) round($value);
        }
        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        $normalized = preg_replace('/[^0-9,-]/', '', (string) $value);
        if ($normalized === null || $normalized === '') {
            return 0;
        }
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);

        return (int) round((float) $normalized);
    }

    private function deductionGroup(string $code, string $name): string
    {
        $text = mb_strtolower($code . ' ' . $name);
        if (str_contains($text, 'impuesto')) {
            return 'Impuesto';
        }
        if (str_contains($text, 'salud') || str_contains($text, 'isapre') || str_contains($text, 'fonasa')) {
            return 'Salud';
        }
        if (str_contains($text, 'afp') || str_contains($text, 'prevision') || str_contains($text, 'previsión') || str_contains($text, 'cesant')) {
            return 'Previsión';
        }
        if (str_contains($text, 'credito') || str_contains($text, 'crédito') || str_contains($text, 'anticipo') || str_contains($text, 'prestamo') || str_contains($text, 'préstamo')) {
            return 'Créditos y anticipos';
        }
        if (str_contains($text, 'ahorro') || str_contains($text, 'apv')) {
            return 'Ahorro';
        }
        if (str_contains($text, 'sind') || str_contains($text, 'bienestar')) {
            return 'Sindicato/Bienestar';
        }

        return 'Otros descuentos';
    }

    private function normalizeRut(?string $rut): string
    {
        return strtoupper((string) preg_replace('/[^0-9kK]/', '', (string) $rut));
    }

    private function periodKey(RemunerationBookImport $import): string
    {
        return sprintf('%04d-%02d', (int) $import->year, (int) $import->month);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function importPayload(?RemunerationBookImport $import): ?array
    {
        if (!$import) {
            return null;
        }

        return [
            'id' => $import->id,
            'period_id' => $import->period_id,
            'period' => $import->period?->name ?: sprintf('%04d-%02d', $import->year, $import->month),
            'year' => $import->year,
            'month' => $import->month,
            'original_filename' => $import->original_filename,
            'row_count' => $import->row_count,
            'imported_at' => $import->imported_at?->format('Y-m-d H:i'),
            'institution' => $import->metadata['institution'] ?? null,
            'rbd' => $import->metadata['rbd'] ?? null,
        ];
    }
}
