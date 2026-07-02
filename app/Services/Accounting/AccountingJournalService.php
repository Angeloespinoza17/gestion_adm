<?php

namespace App\Services\Accounting;

use App\Models\Accounting\AccountingExpense;
use App\Models\Accounting\AccountingIncome;
use App\Models\Accounting\AccountingJournalEntry;
use App\Models\Accounting\AccountingManualAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingJournalService
{
    public function syncForIncome(AccountingIncome $income): void
    {
        if (!$income->manualAccount) {
            return;
        }

        $bankAccount = $this->findBankAccount();
        $entry = $this->findOrCreateEntry($income, 'ING');

        DB::transaction(function () use ($income, $bankAccount, $entry) {
            $entry->fill([
                'entry_date' => $income->received_at,
                'description' => 'Ingreso ' . $income->code,
                'status' => 'registrado',
                'updated_by' => $income->updated_by,
            ])->save();

            $entry->lines()->delete();
            $entry->lines()->createMany([
                [
                    'manual_account_id' => $bankAccount?->id,
                    'cost_center_id' => $income->cost_center_id,
                    'funding_source_id' => $income->funding_source_id,
                    'line_description' => 'Entrada a caja y bancos',
                    'debit' => $income->amount,
                    'credit' => 0,
                ],
                [
                    'manual_account_id' => $income->manual_account_id,
                    'cost_center_id' => $income->cost_center_id,
                    'funding_source_id' => $income->funding_source_id,
                    'line_description' => 'Ingreso operacional',
                    'debit' => 0,
                    'credit' => $income->amount,
                ],
            ]);
        });
    }

    public function syncForExpense(AccountingExpense $expense): void
    {
        if (!$expense->manualAccount) {
            return;
        }

        $bankAccount = $this->findBankAccount();
        $entry = $this->findOrCreateEntry($expense, 'EGR');

        DB::transaction(function () use ($expense, $bankAccount, $entry) {
            $entry->fill([
                'entry_date' => $expense->expense_date,
                'description' => 'Egreso ' . $expense->code,
                'status' => 'registrado',
                'updated_by' => $expense->updated_by,
            ])->save();

            $entry->lines()->delete();
            $entry->lines()->createMany([
                [
                    'manual_account_id' => $expense->manual_account_id,
                    'cost_center_id' => $expense->cost_center_id,
                    'funding_source_id' => $expense->funding_source_id,
                    'line_description' => 'Gasto operacional',
                    'debit' => $expense->total_amount,
                    'credit' => 0,
                ],
                [
                    'manual_account_id' => $bankAccount?->id,
                    'cost_center_id' => $expense->cost_center_id,
                    'funding_source_id' => $expense->funding_source_id,
                    'line_description' => 'Salida desde caja y bancos',
                    'debit' => 0,
                    'credit' => $expense->total_amount,
                ],
            ]);
        });
    }

    public function deleteForSource(Model $source): void
    {
        $entry = AccountingJournalEntry::query()
            ->where('sourceable_type', $source->getMorphClass())
            ->where('sourceable_id', $source->getKey())
            ->first();

        if ($entry) {
            $entry->lines()->delete();
            $entry->delete();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function assertBalanced(array $lines): void
    {
        $debit = collect($lines)->sum(fn (array $line) => (float) ($line['debit'] ?? 0));
        $credit = collect($lines)->sum(fn (array $line) => (float) ($line['credit'] ?? 0));

        if (round($debit, 2) !== round($credit, 2)) {
            throw ValidationException::withMessages([
                'lines' => 'El asiento contable no cuadra. Debe y haber deben coincidir.',
            ]);
        }
    }

    private function findOrCreateEntry(Model $source, string $prefix): AccountingJournalEntry
    {
        /** @var AccountingJournalEntry $entry */
        $entry = AccountingJournalEntry::query()->firstOrNew([
            'sourceable_type' => $source->getMorphClass(),
            'sourceable_id' => $source->getKey(),
        ]);

        if (!$entry->exists) {
            $entry->entry_number = sprintf('ASC-%s-%06d', $prefix, $source->getKey());
            $entry->created_by = $source->created_by;
        }

        return $entry;
    }

    private function findBankAccount(): ?AccountingManualAccount
    {
        return AccountingManualAccount::query()
            ->where('code', '1101')
            ->orWhere(function ($query) {
                $query->where('type', 'activo')->where('category', 'Caja y bancos');
            })
            ->orderBy('id')
            ->first();
    }
}
