<?php

namespace App\Services\Accounting;

use App\Models\Accounting\AccountingBudgetLine;
use App\Models\Accounting\AccountingCashFund;
use App\Models\Accounting\AccountingCheque;
use App\Models\Accounting\AccountingDeclaration;
use App\Models\Accounting\AccountingExpense;
use App\Models\Accounting\AccountingF29Declaration;
use App\Models\Accounting\AccountingIncome;
use App\Models\Accounting\AccountingJournalEntryLine;
use App\Models\Accounting\AccountingPayable;
use App\Models\Accounting\AccountingRendering;
use Illuminate\Support\Collection;

class AccountingReportService
{
    public function build(?int $year = null): array
    {
        $year ??= (int) now()->year;

        return [
            'budget_execution' => $this->budgetExecution($year),
            'incomes_by_source' => $this->incomesBySource($year),
            'expenses_by_center' => $this->expensesByCenter($year),
            'renderings_pending' => AccountingRendering::query()->whereIn('status', ['pendiente_revision', 'observado', 'pendiente'])->orderBy('period_label')->get(),
            'payables' => AccountingPayable::query()->with(['party:id,name'])->orderBy('due_date')->get(),
            'cheques' => AccountingCheque::query()->with(['bankAccount:id,bank_name,account_number'])->orderByDesc('issued_at')->get(),
            'cash_funds' => AccountingCashFund::query()->with(['responsible:id,name'])->orderByDesc('delivered_at')->get(),
            'internal_f29' => AccountingF29Declaration::query()->with(['taxPeriod:id,year,month'])->latest('id')->get(),
            'balance_8_columns' => $this->balanceColumns(),
            'balance_9_columns' => $this->balanceColumns(includeResult: true),
            'declarations' => AccountingDeclaration::query()->with(['type:id,name,category'])->latest('year')->get(),
        ];
    }

    public function exportCsv(string $report, ?int $year = null): string
    {
        $sections = $this->build($year);
        $rows = match ($report) {
            'budget_execution' => $sections['budget_execution'],
            'incomes_by_source' => $sections['incomes_by_source'],
            'expenses_by_center' => $sections['expenses_by_center'],
            'payables' => $sections['payables']->map(fn ($item) => [
                'codigo' => $item->code,
                'proveedor' => $item->party?->name,
                'monto' => $item->amount,
                'vencimiento' => optional($item->due_date)->format('Y-m-d'),
                'estado' => $item->status,
            ]),
            default => collect(),
        };

        return $this->toCsv($rows instanceof Collection ? $rows : collect($rows));
    }

    private function budgetExecution(int $year): Collection
    {
        return AccountingBudgetLine::query()
            ->with(['budget:id,year,name,status', 'costCenter:id,name', 'fundingSource:id,name', 'manualAccount:id,code,name'])
            ->whereHas('budget', fn ($query) => $query->where('year', $year))
            ->get()
            ->map(fn ($line) => [
                'presupuesto' => $line->budget?->name,
                'centro_costo' => $line->costCenter?->name,
                'subvencion' => $line->fundingSource?->name,
                'cuenta' => $line->manualAccount ? $line->manualAccount->code . ' - ' . $line->manualAccount->name : null,
                'monto_planificado' => (float) $line->planned_amount,
                'monto_ejecutado' => (float) $line->executed_amount,
                'diferencia' => round((float) $line->planned_amount - (float) $line->executed_amount, 2),
            ]);
    }

    private function incomesBySource(int $year): Collection
    {
        return AccountingIncome::query()
            ->with('fundingSource:id,name')
            ->whereYear('received_at', $year)
            ->get()
            ->groupBy(fn ($item) => $item->fundingSource?->name ?? 'Sin fuente')
            ->map(fn (Collection $items, string $label) => [
                'fuente' => $label,
                'monto' => round((float) $items->sum('amount'), 2),
                'registros' => $items->count(),
            ])
            ->values();
    }

    private function expensesByCenter(int $year): Collection
    {
        return AccountingExpense::query()
            ->with('costCenter:id,name')
            ->whereYear('expense_date', $year)
            ->get()
            ->groupBy(fn ($item) => $item->costCenter?->name ?? 'Sin centro de costo')
            ->map(fn (Collection $items, string $label) => [
                'centro_costo' => $label,
                'monto' => round((float) $items->sum('total_amount'), 2),
                'registros' => $items->count(),
            ])
            ->values();
    }

    private function balanceColumns(bool $includeResult = false): Collection
    {
        return AccountingJournalEntryLine::query()
            ->with('manualAccount:id,code,name,type')
            ->get()
            ->groupBy('manual_account_id')
            ->map(function (Collection $lines) use ($includeResult) {
                $account = $lines->first()?->manualAccount;
                $debit = round((float) $lines->sum('debit'), 2);
                $credit = round((float) $lines->sum('credit'), 2);
                $difference = round($debit - $credit, 2);

                return [
                    'cuenta' => $account ? $account->code . ' - ' . $account->name : 'Sin cuenta',
                    'debitos' => $debit,
                    'creditos' => $credit,
                    'saldo_deudor' => $difference > 0 ? $difference : 0,
                    'saldo_acreedor' => $difference < 0 ? abs($difference) : 0,
                    'activo' => $account?->type === 'activo' ? max($difference, 0) : 0,
                    'pasivo' => $account?->type === 'pasivo' ? max(abs($difference), 0) : 0,
                    'perdidas' => $account?->type === 'egreso' ? max($difference, 0) : 0,
                    'ganancias' => $account?->type === 'ingreso' ? max(abs($difference), 0) : 0,
                    'resultado' => $includeResult ? $difference : null,
                ];
            })
            ->values();
    }

    private function toCsv(Collection $rows): string
    {
        if ($rows->isEmpty()) {
            return "sin_datos\n";
        }

        $headers = array_keys((array) $rows->first());
        $lines = [implode(',', $headers)];

        foreach ($rows as $row) {
            $values = [];
            foreach ((array) $row as $value) {
                $text = str_replace('"', '""', (string) $value);
                $values[] = '"' . $text . '"';
            }
            $lines[] = implode(',', $values);
        }

        return implode("\n", $lines) . "\n";
    }
}
