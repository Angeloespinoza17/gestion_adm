<?php

namespace App\Services\Accounting;

use App\Models\Accounting\AccountingBankAccount;
use App\Models\Accounting\AccountingBudget;
use App\Models\Accounting\AccountingBudgetLine;
use App\Models\Accounting\AccountingCashFund;
use App\Models\Accounting\AccountingCheque;
use App\Models\Accounting\AccountingExpense;
use App\Models\Accounting\AccountingF29Declaration;
use App\Models\Accounting\AccountingFundingSource;
use App\Models\Accounting\AccountingIncome;
use App\Models\Accounting\AccountingPayable;
use App\Models\Accounting\AccountingRendering;
use Carbon\Carbon;

class AccountingDashboardService
{
    public function build(?int $year = null, ?int $month = null): array
    {
        $today = Carbon::today();
        $year ??= (int) $today->year;

        $incomeQuery = AccountingIncome::query()->whereYear('received_at', $year);
        $expenseQuery = AccountingExpense::query()->whereYear('expense_date', $year);

        if ($month) {
            $incomeQuery->whereMonth('received_at', $month);
            $expenseQuery->whereMonth('expense_date', $month);
        }

        $incomeAmount = (float) $incomeQuery->sum('amount');
        $expenseAmount = (float) $expenseQuery->sum('total_amount');
        $approvedBudget = (float) AccountingBudgetLine::query()
            ->whereHas('budget', fn ($query) => $query->where('year', $year)->where('status', 'aprobado'))
            ->sum('planned_amount');
        $budgetExecution = $approvedBudget > 0 ? round(($expenseAmount / $approvedBudget) * 100, 2) : 0;

        return [
            'metrics' => [
                'year' => $year,
                'month' => $month,
                'income_amount' => round($incomeAmount, 2),
                'expense_amount' => round($expenseAmount, 2),
                'available_balance' => round((float) AccountingBankAccount::query()->sum('current_balance') + (float) AccountingCashFund::query()->sum('current_balance'), 2),
                'approved_budget' => round($approvedBudget, 2),
                'budget_execution' => round($expenseAmount, 2),
                'budget_execution_percentage' => $budgetExecution,
                'pending_payables' => (int) AccountingPayable::query()->whereIn('status', ['pendiente', 'programada', 'vencida'])->count(),
                'pending_renderings' => (int) AccountingRendering::query()->whereIn('status', ['pendiente_revision', 'observado', 'pendiente'])->count(),
                'pending_cheques' => (int) AccountingCheque::query()->whereIn('status', ['emitido', 'entregado', 'vencido'])->count(),
                'pending_f29' => (int) AccountingF29Declaration::query()->whereIn('status', ['pendiente', 'en_preparacion', 'observado'])->count(),
            ],
            'alerts' => [
                'payables_due_soon' => AccountingPayable::query()->whereBetween('due_date', [$today, $today->copy()->addDays(7)])->whereIn('status', ['pendiente', 'programada'])->count(),
                'overdue_payables' => AccountingPayable::query()->whereDate('due_date', '<', $today)->whereIn('status', ['pendiente', 'programada'])->count(),
                'funds_expiring' => AccountingCashFund::query()->whereDate('due_at', '<=', $today->copy()->addDays(7))->whereIn('status', ['abierto', 'pendiente_rendicion'])->count(),
                'reconciliation_pending' => \App\Models\Accounting\AccountingBankMovement::query()->where('is_reconciled', false)->count(),
                'invoices_pending_payment' => AccountingExpense::query()->where('document_type', 'factura')->whereNotIn('status', ['pagado', 'anulado'])->count(),
            ],
            'summaries' => [
                'funding_sources' => AccountingFundingSource::query()
                    ->orderBy('name')
                    ->get()
                    ->map(function (AccountingFundingSource $source) use ($year) {
                        $income = (float) AccountingIncome::query()->where('funding_source_id', $source->id)->whereYear('received_at', $year)->sum('amount');
                        $expense = (float) AccountingExpense::query()->where('funding_source_id', $source->id)->whereYear('expense_date', $year)->sum('total_amount');

                        return [
                            'label' => $source->name,
                            'income' => round($income, 2),
                            'expense' => round($expense, 2),
                            'balance' => round($income - $expense, 2),
                        ];
                    })
                    ->all(),
                'cost_centers' => \App\Models\Accounting\AccountingCostCenter::query()
                    ->orderBy('name')
                    ->get()
                    ->map(function (\App\Models\Accounting\AccountingCostCenter $center) use ($year) {
                        $expense = (float) AccountingExpense::query()->where('cost_center_id', $center->id)->whereYear('expense_date', $year)->sum('total_amount');
                        $budget = (float) AccountingBudgetLine::query()
                            ->where('cost_center_id', $center->id)
                            ->whereHas('budget', fn ($query) => $query->where('year', $year))
                            ->sum('planned_amount');

                        return [
                            'label' => $center->name,
                            'budget' => round($budget, 2),
                            'expense' => round($expense, 2),
                            'variance' => round($budget - $expense, 2),
                        ];
                    })
                    ->all(),
            ],
            'recent' => [
                'incomes' => AccountingIncome::query()->with(['fundingSource:id,name', 'costCenter:id,name'])->latest('received_at')->limit(6)->get(),
                'expenses' => AccountingExpense::query()->with(['party:id,name', 'costCenter:id,name'])->latest('expense_date')->limit(6)->get(),
                'payables' => AccountingPayable::query()->with(['party:id,name'])->orderBy('due_date')->limit(6)->get(),
            ],
        ];
    }
}
