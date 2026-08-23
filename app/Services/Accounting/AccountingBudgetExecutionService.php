<?php

namespace App\Services\Accounting;

use App\Models\Accounting\AccountingBudgetExecutionImport;
use App\Models\Accounting\AccountingBudgetExecutionLine;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingBudgetExecutionService
{
    private const MONTH_LABELS = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
    ];

    public function __construct(
        private readonly BudgetExecutionWorkbookParser $parser,
        private readonly AccountingAuditService $auditService,
    ) {}

    /** @return array<string, mixed> */
    public function import(UploadedFile $file, int $year, User $user, Request $request): array
    {
        $parsed = $this->parser->parse($file->getRealPath());
        if ($parsed['detected_year'] !== null && $parsed['detected_year'] !== $year) {
            throw ValidationException::withMessages([
                'year' => "El libro corresponde al año {$parsed['detected_year']}; seleccionaste {$year}.",
            ]);
        }

        $hash = hash_file('sha256', $file->getRealPath());
        $result = DB::transaction(function () use ($parsed, $file, $hash, $year, $user, $request) {
            $previous = AccountingBudgetExecutionImport::query()->where('year', $year)->lockForUpdate()->first();
            $previousSummary = $previous ? [
                'id' => $previous->id,
                'year' => $previous->year,
                'original_filename' => $previous->original_filename,
                'sha256' => $previous->sha256,
                'line_count' => $previous->line_count,
                'imported_at' => $previous->imported_at?->toIso8601String(),
            ] : [];
            $previous?->delete();

            $import = AccountingBudgetExecutionImport::query()->create([
                'year' => $year,
                'school_name' => $parsed['school_name'],
                'original_filename' => mb_substr($file->getClientOriginalName(), 0, 255),
                'sha256' => $hash,
                'reported_through_month' => $parsed['reported_through_month'],
                'line_count' => count($parsed['lines']),
                'metadata' => [
                    'source_sheets' => $parsed['sheets'],
                    'detected_year' => $parsed['detected_year'],
                    'parser_version' => '1.0',
                ],
                'imported_by' => $user->id,
                'imported_at' => now(),
            ]);

            $now = now();
            $rows = array_map(static fn (array $line): array => [
                ...$line,
                'import_id' => $import->id,
                'created_at' => $now,
                'updated_at' => $now,
            ], $parsed['lines']);
            foreach (array_chunk($rows, 250) as $chunk) {
                AccountingBudgetExecutionLine::query()->insert($chunk);
            }

            $this->auditService->log(
                $previous ? 'reemplazar_ejecucion_presupuestaria' : 'importar_ejecucion_presupuestaria',
                $import,
                $user,
                $previousSummary,
                [
                    'year' => $year,
                    'original_filename' => $import->original_filename,
                    'sha256' => $hash,
                    'line_count' => $import->line_count,
                ],
                "Importación anual de ejecución presupuestaria {$year}.",
                $request,
            );

            return ['import' => $import, 'replaced' => $previousSummary !== []];
        }, 3);
        $import = $result['import'];

        return [
            'message' => "La ejecución presupuestaria {$year} fue importada correctamente.",
            'replaced' => $result['replaced'],
            'data' => $this->dashboard($year),
            'import_id' => $import->id,
        ];
    }

    /** @return array<string, mixed> */
    public function dashboard(?int $year = null): array
    {
        $availableYears = AccountingBudgetExecutionImport::query()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($value): int => (int) $value)
            ->all();
        $selectedYear = $year ?: ($availableYears[0] ?? (int) now()->year);
        $import = AccountingBudgetExecutionImport::query()
            ->with(['importer:id,name'])
            ->where('year', $selectedYear)
            ->first();

        if (! $import) {
            return $this->emptyDashboard($selectedYear, $availableYears);
        }

        $lines = AccountingBudgetExecutionLine::query()
            ->where('import_id', $import->id)
            ->orderBy('sort_order')
            ->get();
        $accounts = $lines->map(fn (AccountingBudgetExecutionLine $line): array => $this->accountPayload($line));
        $incomeAccounts = $accounts->where('flow_type', 'income');
        $expenseAccounts = $accounts->where('flow_type', 'expense');
        $incomeBudget = $this->sum($incomeAccounts, 'annual_budget');
        $incomeExecuted = $this->sum($incomeAccounts, 'executed');
        $expenseBudget = $this->sum($expenseAccounts, 'annual_budget');
        $expenseExecuted = $this->sum($expenseAccounts, 'executed');
        $reportedMonth = $import->reported_through_month;
        $projectedExpenses = $reportedMonth ? ($expenseExecuted / $reportedMonth) * 12 : 0;

        $monthly = [];
        $cumulativeBalance = 0;
        foreach (AccountingBudgetExecutionLine::MONTH_COLUMNS as $index => $column) {
            $monthIncome = round((float) $lines->where('flow_type', 'income')->sum(fn ($line) => (float) ($line->{$column} ?? 0)), 2);
            $monthExpense = round((float) $lines->where('flow_type', 'expense')->sum(fn ($line) => (float) ($line->{$column} ?? 0)), 2);
            $cumulativeBalance += $monthIncome - $monthExpense;
            $monthly[] = [
                'month' => $index + 1,
                'label' => self::MONTH_LABELS[$index],
                'short_label' => mb_substr(self::MONTH_LABELS[$index], 0, 3),
                'income' => $monthIncome,
                'expense' => $monthExpense,
                'balance' => round($monthIncome - $monthExpense, 2),
                'cumulative_balance' => round($cumulativeBalance, 2),
            ];
        }

        $subsidies = $accounts
            ->groupBy('subsidy_code')
            ->map(function (Collection $items): array {
                $income = $items->where('flow_type', 'income');
                $expense = $items->where('flow_type', 'expense');
                $expenseBudget = $this->sum($expense, 'annual_budget');
                $expenseExecuted = $this->sum($expense, 'executed');

                return [
                    'code' => $items->first()['subsidy_code'],
                    'name' => $items->first()['subsidy_name'],
                    'income_budget' => $this->sum($income, 'annual_budget'),
                    'income_executed' => $this->sum($income, 'executed'),
                    'expense_budget' => $expenseBudget,
                    'expense_executed' => $expenseExecuted,
                    'available' => round($expenseBudget - $expenseExecuted, 2),
                    'execution_percentage' => $this->percentage($expenseExecuted, $expenseBudget),
                ];
            })
            ->sortByDesc('expense_budget')
            ->values()
            ->all();

        $categories = $expenseAccounts
            ->groupBy(fn (array $item): string => $item['subsidy_code'].'|'.$item['category'])
            ->map(function (Collection $items): array {
                $budget = $this->sum($items, 'annual_budget');
                $executed = $this->sum($items, 'executed');

                return [
                    'subsidy_code' => $items->first()['subsidy_code'],
                    'subsidy_name' => $items->first()['subsidy_name'],
                    'category' => $items->first()['category'],
                    'budget' => $budget,
                    'executed' => $executed,
                    'variance' => round($budget - $executed, 2),
                    'execution_percentage' => $this->percentage($executed, $budget),
                ];
            })
            ->sortByDesc('executed')
            ->values()
            ->all();

        $overExecuted = $expenseAccounts->filter(fn (array $account): bool => $account['annual_budget'] > 0 && $account['executed'] > $account['annual_budget'])->count();

        return [
            'year' => (int) $import->year,
            'available_years' => $availableYears,
            'has_data' => true,
            'import' => [
                'id' => $import->id,
                'school_name' => $import->school_name,
                'original_filename' => $import->original_filename,
                'reported_through_month' => $reportedMonth,
                'reported_through_label' => $reportedMonth ? self::MONTH_LABELS[$reportedMonth - 1] : 'Sin movimientos informados',
                'line_count' => $import->line_count,
                'imported_at' => $import->imported_at?->toIso8601String(),
                'imported_by' => $import->importer?->name,
                'source_sheets' => $import->metadata['source_sheets'] ?? [],
            ],
            'metrics' => [
                'income_budget' => $incomeBudget,
                'income_executed' => $incomeExecuted,
                'income_achievement_percentage' => $this->percentage($incomeExecuted, $incomeBudget),
                'expense_budget' => $expenseBudget,
                'expense_executed' => $expenseExecuted,
                'expense_execution_percentage' => $this->percentage($expenseExecuted, $expenseBudget),
                'available_budget' => round($expenseBudget - $expenseExecuted, 2),
                'net_result' => round($incomeExecuted - $expenseExecuted, 2),
                'projected_expenses' => round($projectedExpenses, 2),
                'projected_variance' => round($expenseBudget - $projectedExpenses, 2),
            ],
            'alerts' => [
                'over_executed_accounts' => $overExecuted,
                'unbudgeted_movements' => $accounts->filter(fn (array $account): bool => $account['annual_budget'] == 0 && $account['executed'] != 0)->count(),
            ],
            'monthly' => $monthly,
            'subsidies' => $subsidies,
            'categories' => $categories,
            'accounts' => $accounts->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function emptyDashboard(int $year, array $availableYears): array
    {
        return [
            'year' => $year,
            'available_years' => $availableYears,
            'has_data' => false,
            'import' => null,
            'metrics' => [],
            'alerts' => ['over_executed_accounts' => 0, 'unbudgeted_movements' => 0],
            'monthly' => [],
            'subsidies' => [],
            'categories' => [],
            'accounts' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function accountPayload(AccountingBudgetExecutionLine $line): array
    {
        $executed = round(array_sum(array_map(fn (string $month): float => (float) ($line->{$month} ?? 0), AccountingBudgetExecutionLine::MONTH_COLUMNS)), 2);
        $budget = round((float) ($line->annual_budget ?? 0), 2);

        return [
            'id' => $line->id,
            'subsidy_code' => $line->subsidy_code,
            'subsidy_name' => $line->subsidy_name,
            'flow_type' => $line->flow_type,
            'category' => $line->category,
            'account_name' => $line->account_name,
            'annual_budget' => $budget,
            'executed' => $executed,
            'variance' => round($budget - $executed, 2),
            'execution_percentage' => $this->percentage($executed, $budget),
            'monthly' => array_map(fn (string $month): float => round((float) ($line->{$month} ?? 0), 2), AccountingBudgetExecutionLine::MONTH_COLUMNS),
        ];
    }

    private function sum(Collection $items, string $key): float
    {
        return round((float) $items->sum(fn (array $item): float => (float) ($item[$key] ?? 0)), 2);
    }

    private function percentage(float $value, float $base): float
    {
        return $base == 0.0 ? 0.0 : round(($value / $base) * 100, 1);
    }
}
