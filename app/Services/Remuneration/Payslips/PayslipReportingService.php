<?php

namespace App\Services\Remuneration\Payslips;

use App\Models\LibroDigital\School;
use App\Models\Remuneration\RemunerationBookImport;
use App\Models\Remuneration\RemunerationPayslip;
use App\Models\Remuneration\RemunerationPayslipControl;
use App\Models\Remuneration\RemunerationPayslipDiscount;
use App\Models\Remuneration\RemunerationPayslipEarning;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PayslipReportingService
{
    /** @param array<string,mixed> $filters */
    public function dashboard(array $filters): array
    {
        $scope = $this->payslips($filters);
        $metrics = (clone $scope)->selectRaw(
            'COUNT(*) as payslips, COUNT(DISTINCT staff_id) as workers, '
            .'COALESCE(SUM(gross_total),0) as gross_total, COALESCE(SUM(total_deductions),0) as deduction_total, '
            .'COALESCE(SUM(net_amount),0) as net_total, COALESCE(SUM(employer_contributions),0) as employer_total, '
            .'COALESCE(SUM(total_cost),0) as total_cost'
        )->first();

        $funding = DB::table('remuneration_payslip_funding_summaries as s')
            ->join('remuneration_payslips as p', 'p.id', '=', 's.payslip_id')
            ->join('accounting_funding_sources as f', 'f.id', '=', 's.funding_source_id')
            ->where('p.is_current', true)
            ->where('p.status', 'importada');
        $this->applySqlScope($funding, $filters, 'p');
        $funding = $funding->groupBy('f.id', 'f.code', 'f.name')
            ->orderByDesc(DB::raw('SUM(s.net_amount)'))
            ->selectRaw(
                'f.id, f.code, f.name, SUM(s.taxable_earnings) as taxable_earnings, '
                .'SUM(s.non_taxable_earnings) as non_taxable_earnings, SUM(s.gross_earnings) as gross_earnings, '
                .'SUM(s.legal_deductions) as legal_deductions, SUM(s.other_deductions) as other_deductions, '
                .'SUM(s.net_amount) as net_amount, SUM(s.employer_contributions) as employer_contributions, '
                .'SUM(s.total_cost) as total_cost'
            )->get()->map(fn ($row): array => collect((array) $row)->map(fn ($value, $key) => in_array($key, ['id', 'code', 'name'], true) ? $value : (int) $value)->all())->all();

        $monthly = (clone $scope)
            ->groupBy('year', 'month')
            ->orderBy('year')->orderBy('month')
            ->selectRaw('year, month, COUNT(*) as payslips, SUM(net_amount) as net_amount, SUM(employer_contributions) as employer_contributions, SUM(total_cost) as total_cost')
            ->get()->map(fn ($row): array => [
                'year' => (int) $row->year,
                'month' => (int) $row->month,
                'payslips' => (int) $row->payslips,
                'net_amount' => (int) $row->net_amount,
                'employer_contributions' => (int) $row->employer_contributions,
                'total_cost' => (int) $row->total_cost,
            ])->all();

        $destinations = DB::table('remuneration_payslip_discounts as d')
            ->join('remuneration_payslips as p', 'p.id', '=', 'd.payslip_id')
            ->where('p.is_current', true)->where('p.status', 'importada');
        $this->applySqlScope($destinations, $filters, 'p');
        $destinations = $destinations->groupBy('d.payment_destination')
            ->orderByDesc(DB::raw('SUM(d.amount)'))
            ->selectRaw("COALESCE(d.payment_destination, 'Pendiente') as destination, SUM(d.amount) as amount")
            ->get()->map(fn ($row): array => ['destination' => $row->destination, 'amount' => (int) $row->amount])->all();

        $errorCount = DB::table('remuneration_payslip_issues as i')
            ->join('remuneration_payslips as p', 'p.id', '=', 'i.payslip_id')
            ->where('i.status', 'abierta')->where('i.severity', 'error');
        $this->applySqlScope($errorCount, $filters, 'p');

        return [
            'metrics' => [
                'payslips' => (int) ($metrics->payslips ?? 0),
                'workers' => (int) ($metrics->workers ?? 0),
                'gross_total' => (int) ($metrics->gross_total ?? 0),
                'deduction_total' => (int) ($metrics->deduction_total ?? 0),
                'net_total' => (int) ($metrics->net_total ?? 0),
                'employer_total' => (int) ($metrics->employer_total ?? 0),
                'total_cost' => (int) ($metrics->total_cost ?? 0),
                'error_count' => $errorCount->count(),
            ],
            'funding_sources' => $funding,
            'monthly' => $monthly,
            'discount_destinations' => $destinations,
        ];
    }

    /** @param array<string,mixed> $filters */
    public function history(array $filters): LengthAwarePaginator
    {
        $query = $this->payslips($filters)
            ->with([
                'staff:id,full_name,rut',
                'school:id,name,rbd',
                'period:id,name,year,month',
                'fundingSummaries.fundingSource:id,code,name',
                'controls:id,payslip_id,code,status,difference',
            ])
            ->orderByDesc('year')->orderByDesc('month')->orderByDesc('id');

        return $query->paginate((int) ($filters['per_page'] ?? 20))->through(fn (RemunerationPayslip $payslip): array => $this->payslipRow($payslip));
    }

    /** @param array<string,mixed> $filters */
    public function matrix(string $type, array $filters): LengthAwarePaginator
    {
        if ($type === 'workers') {
            return $this->history($filters);
        }

        $perPage = (int) ($filters['per_page'] ?? 30);
        if ($type === 'earnings') {
            $query = RemunerationPayslipEarning::query()
                ->with(['payslip.staff:id,full_name,rut', 'payslip.period:id,name,year,month', 'fundingSource:id,code,name'])
                ->whereHas('payslip', fn (Builder $query) => $this->applyEloquentScope($query->where('is_current', true)->where('status', 'importada'), $filters))
                ->when($filters['funding_source_id'] ?? null, fn (Builder $query, $value) => $query->where('funding_source_id', $value))
                ->when($filters['concept'] ?? null, fn (Builder $query, $value) => $query->where(fn (Builder $nested) => $nested->where('code', 'like', '%'.$value.'%')->orWhere('description', 'like', '%'.$value.'%')))
                ->orderByDesc('payslip_id')->orderBy('line_number');

            return $query->paginate($perPage)->through(fn ($line): array => [
                'period' => $line->payslip?->period?->name,
                'worker' => $line->payslip?->staff?->full_name,
                'rut' => $this->maskRut($line->payslip?->staff?->rut),
                'code' => $line->code,
                'description' => $line->description,
                'is_imponible' => (bool) $line->is_imponible,
                'funding_source' => $line->fundingSource?->code,
                'amount' => (int) $line->amount,
                'page' => (int) $line->page_number,
            ]);
        }

        if ($type === 'discounts') {
            $query = RemunerationPayslipDiscount::query()
                ->with(['payslip.staff:id,full_name,rut', 'payslip.period:id,name', 'allocations.fundingSource:id,code,name'])
                ->whereHas('payslip', fn (Builder $query) => $this->applyEloquentScope($query->where('is_current', true)->where('status', 'importada'), $filters))
                ->when($filters['concept'] ?? null, fn (Builder $query, $value) => $query->where(fn (Builder $nested) => $nested->where('code', 'like', '%'.$value.'%')->orWhere('description', 'like', '%'.$value.'%')))
                ->orderByDesc('payslip_id')->orderBy('line_number');

            return $query->paginate($perPage)->through(function (RemunerationPayslipDiscount $row): array {
                return [
                    'period' => $row->payslip?->period?->name,
                    'worker' => $row->payslip?->staff?->full_name,
                    'rut' => $this->maskRut($row->payslip?->staff?->rut),
                    'code' => $row->code,
                    'description' => $row->description,
                    'classification' => $row->classification,
                    'destination' => $row->payment_destination,
                    'distribution_base' => $row->distribution_base,
                    'amount' => (int) $row->amount,
                    'allocations' => $row->allocations->map(fn ($allocation): array => [
                        'funding_source' => $allocation->fundingSource?->code,
                        'base_amount' => (int) $allocation->base_amount,
                        'proportion' => (float) $allocation->proportion,
                        'calculated_amount' => (float) $allocation->calculated_amount,
                        'rounding_adjustment' => (int) $allocation->rounding_adjustment,
                        'assigned_amount' => (int) $allocation->assigned_amount,
                    ])->all(),
                ];
            });
        }

        if ($type === 'contributions') {
            $query = DB::table('remuneration_payslip_employer_contributions as c')
                ->join('remuneration_payslips as p', 'p.id', '=', 'c.payslip_id')
                ->leftJoin('staff as st', 'st.id', '=', 'p.staff_id')
                ->leftJoin('remuneration_periods as rp', 'rp.id', '=', 'p.period_id')
                ->leftJoin('accounting_funding_sources as f', 'f.id', '=', 'c.funding_source_id')
                ->where('p.is_current', true)->where('p.status', 'importada');
            $this->applySqlScope($query, $filters, 'p');

            return $query->orderByDesc('p.year')->orderByDesc('p.month')->orderBy('st.full_name')->orderBy('c.line_number')
                ->select(['rp.name as period', 'st.full_name', 'st.rut', 'c.code', 'c.description', 'f.code as funding_source', 'c.amount', 'c.page_number'])
                ->paginate($perPage)->through(fn ($row): array => [
                    'period' => $row->period,
                    'worker' => $row->full_name,
                    'rut' => $this->maskRut($row->rut),
                    'code' => $row->code,
                    'description' => $row->description,
                    'funding_source' => $row->funding_source,
                    'amount' => (int) $row->amount,
                    'page' => (int) $row->page_number,
                ]);
        }

        $query = RemunerationPayslipControl::query()
            ->with('payslip.staff:id,full_name,rut')
            ->whereHas('payslip', fn (Builder $query) => $this->applyEloquentScope($query->where('is_current', true)->where('status', 'importada'), $filters))
            ->orderByDesc('id');

        return $query->paginate($perPage);
    }

    /** @param array<string,mixed> $filters */
    public function reconciliation(School $school, int $year, int $month, array $filters = []): array
    {
        $book = RemunerationBookImport::query()
            ->with('rows.staff:id,full_name,rut')
            ->where('year', $year)->where('month', $month)->where('status', 'imported')
            ->where('metadata->rbd', $school->rbd)
            ->latest('imported_at')->latest('id')->first();
        $payslips = RemunerationPayslip::query()
            ->with('staff:id,full_name,rut')
            ->where('school_id', $school->id)->where('year', $year)->where('month', $month)
            ->where('is_current', true)->where('status', 'importada')->get();

        $bookByStaff = collect($book?->rows ?? [])->whereNotNull('staff_id')->keyBy('staff_id');
        $payslipByStaff = $payslips->whereNotNull('staff_id')->keyBy('staff_id');
        $staffIds = $bookByStaff->keys()->merge($payslipByStaff->keys())->unique()->sort()->values();
        $rows = $staffIds->map(function ($staffId) use ($bookByStaff, $payslipByStaff): array {
            $bookRow = $bookByStaff->get($staffId);
            $payslip = $payslipByStaff->get($staffId);
            $staff = $payslip?->staff ?: $bookRow?->staff;
            $values = [
                'gross_total' => [(int) ($bookRow?->gross_total ?? 0), (int) ($payslip?->gross_total ?? 0)],
                'gross_taxable' => [(int) ($bookRow?->gross_taxable_amount ?? 0), (int) ($payslip?->gross_taxable_amount ?? 0)],
                'gross_non_taxable' => [(int) ($bookRow?->gross_non_taxable_amount ?? 0), (int) ($payslip?->gross_non_taxable_amount ?? 0)],
                'deductions' => [(int) ($bookRow?->total_deductions ?? 0), (int) ($payslip?->total_deductions ?? 0)],
                'net_amount' => [(int) ($bookRow?->net_amount ?? 0), (int) ($payslip?->net_amount ?? 0)],
                'employer_contributions' => [(int) ($bookRow?->employer_contributions ?? 0), (int) ($payslip?->employer_contributions ?? 0)],
            ];

            return [
                'staff_id' => (int) $staffId,
                'worker' => $staff?->full_name,
                'rut' => $this->maskRut($staff?->rut),
                'presence' => $bookRow && $payslip ? 'ambas' : ($bookRow ? 'solo_libro' : 'solo_liquidacion'),
                'values' => collect($values)->map(fn (array $pair): array => ['book' => $pair[0], 'payslip' => $pair[1], 'difference' => $pair[1] - $pair[0]])->all(),
                'status' => collect($values)->every(fn (array $pair): bool => $pair[0] === $pair[1]) ? 'correcto' : 'diferencia',
            ];
        })->values();

        $bookTotals = [
            'workers' => $book?->rows?->count() ?? 0,
            'gross_total' => (int) ($book?->gross_total ?? 0),
            'deductions' => (int) ($book?->total_deductions ?? 0),
            'net_amount' => (int) ($book?->net_total ?? 0),
            'employer_contributions' => (int) ($book?->employer_contributions ?? 0),
        ];
        $payslipTotals = [
            'workers' => $payslips->count(),
            'gross_total' => (int) $payslips->sum('gross_total'),
            'deductions' => (int) $payslips->sum('total_deductions'),
            'net_amount' => (int) $payslips->sum('net_amount'),
            'employer_contributions' => (int) $payslips->sum('employer_contributions'),
        ];

        return [
            'book_import' => $book ? ['id' => $book->id, 'filename' => $book->original_filename, 'imported_at' => $book->imported_at?->format('Y-m-d H:i')] : null,
            'summary' => [
                'book' => $bookTotals,
                'payslips' => $payslipTotals,
                'differences' => collect($payslipTotals)->map(fn (int $value, string $key): int => $value - $bookTotals[$key])->all(),
                'both' => $rows->where('presence', 'ambas')->count(),
                'only_book' => $rows->where('presence', 'solo_libro')->count(),
                'only_payslips' => $rows->where('presence', 'solo_liquidacion')->count(),
            ],
            'rows' => $rows->all(),
            'status' => $book && collect($payslipTotals)->every(fn (int $value, string $key): bool => $value === $bookTotals[$key]) ? 'correcto' : ($book ? 'diferencia' : 'sin_libro'),
        ];
    }

    /** @param array<string,mixed> $filters */
    private function payslips(array $filters): Builder
    {
        return $this->applyEloquentScope(
            RemunerationPayslip::query()->where('is_current', true)->where('status', 'importada'),
            $filters,
        );
    }

    private function applyEloquentScope(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['school_id'] ?? null, fn (Builder $query, $value) => $query->where('school_id', $value))
            ->when($filters['year'] ?? null, fn (Builder $query, $value) => $query->where('year', $value))
            ->when($filters['month'] ?? null, fn (Builder $query, $value) => $query->where('month', $value))
            ->when($filters['month_from'] ?? null, fn (Builder $query, $value) => $query->where('month', '>=', $value))
            ->when($filters['month_to'] ?? null, fn (Builder $query, $value) => $query->where('month', '<=', $value))
            ->when($filters['staff_id'] ?? null, fn (Builder $query, $value) => $query->where('staff_id', $value))
            ->when($filters['reconciliation_status'] ?? null, fn (Builder $query, $value) => $query->where('reconciliation_status', $value))
            ->when($filters['funding_source_id'] ?? null, fn (Builder $query, $value) => $query->whereHas('fundingSummaries', fn (Builder $nested) => $nested->where('funding_source_id', $value)))
            ->when($filters['search'] ?? null, fn (Builder $query, $value) => $query->whereHas('staff', fn (Builder $staff) => $staff->where('full_name', 'like', '%'.$value.'%')))
            ->when($filters['concept'] ?? null, fn (Builder $query, $value) => $query->where(fn (Builder $nested) => $nested
                ->whereHas('earnings', fn (Builder $line) => $line->where('code', 'like', '%'.$value.'%')->orWhere('description', 'like', '%'.$value.'%'))
                ->orWhereHas('discounts', fn (Builder $line) => $line->where('code', 'like', '%'.$value.'%')->orWhere('description', 'like', '%'.$value.'%'))));
    }

    private function applySqlScope($query, array $filters, string $alias): void
    {
        foreach (['school_id', 'year', 'month', 'staff_id', 'reconciliation_status'] as $field) {
            if ($filters[$field] ?? null) {
                $query->where($alias.'.'.$field, $filters[$field]);
            }
        }
        if ($filters['month_from'] ?? null) {
            $query->where($alias.'.month', '>=', $filters['month_from']);
        }
        if ($filters['month_to'] ?? null) {
            $query->where($alias.'.month', '<=', $filters['month_to']);
        }
    }

    private function payslipRow(RemunerationPayslip $payslip): array
    {
        return [
            'id' => $payslip->public_id,
            'year' => $payslip->year,
            'month' => $payslip->month,
            'period' => $payslip->period?->name,
            'worker' => $payslip->staff?->full_name ?: $payslip->employee_name_encrypted,
            'rut' => $payslip->maskedRut(),
            'staff_id' => $payslip->staff_id,
            'school' => $payslip->school?->name,
            'status' => $payslip->status,
            'reconciliation_status' => $payslip->reconciliation_status,
            'version' => $payslip->version,
            'confidence' => (float) $payslip->confidence,
            'gross_taxable' => $payslip->gross_taxable_amount,
            'gross_non_taxable' => $payslip->gross_non_taxable_amount,
            'gross_total' => $payslip->gross_total,
            'deductions' => $payslip->total_deductions,
            'net_amount' => $payslip->net_amount,
            'employer_contributions' => $payslip->employer_contributions,
            'total_cost' => $payslip->total_cost,
            'funding_sources' => $payslip->fundingSummaries->mapWithKeys(fn ($summary): array => [$summary->fundingSource?->code => [
                'taxable' => $summary->taxable_earnings,
                'non_taxable' => $summary->non_taxable_earnings,
                'gross' => $summary->gross_earnings,
                'legal_deductions' => $summary->legal_deductions,
                'other_deductions' => $summary->other_deductions,
                'net' => $summary->net_amount,
                'employer' => $summary->employer_contributions,
                'total_cost' => $summary->total_cost,
            ]])->all(),
            'controls' => $payslip->controls->countBy('status')->all(),
        ];
    }

    private function maskRut(?string $rut): string
    {
        $normalized = preg_replace('/[^0-9Kk]/', '', (string) $rut) ?: '';

        return strlen($normalized) > 4 ? str_repeat('*', strlen($normalized) - 4).substr($normalized, -4) : '****';
    }
}
