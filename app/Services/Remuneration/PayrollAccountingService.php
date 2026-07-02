<?php

namespace App\Services\Remuneration;

use App\Models\Accounting\AccountingJournalEntry;
use App\Models\Accounting\AccountingJournalEntryLine;
use App\Models\Remuneration\RemunerationAccountingExport;
use App\Models\Remuneration\RemunerationContractSetting;
use App\Models\Remuneration\RemunerationPayroll;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollAccountingService
{
    public function __construct(
        private readonly RemunerationAuditService $auditService,
    ) {
    }

    public function centralize(RemunerationPayroll $payroll, ?User $user): RemunerationAccountingExport
    {
        if (!in_array($payroll->status, ['aprobada', 'pagada'], true)) {
            throw ValidationException::withMessages([
                'payroll' => 'Solo se pueden centralizar liquidaciones aprobadas o pagadas.',
            ]);
        }

        $existing = RemunerationAccountingExport::query()
            ->where('payroll_id', $payroll->id)
            ->where('status', 'generado')
            ->first();

        if ($existing) {
            return $existing->load('journalEntry.lines');
        }

        return DB::transaction(function () use ($payroll, $user) {
            $payroll->loadMissing(['period', 'distributions']);
            $setting = RemunerationContractSetting::query()
                ->where('staff_id', $payroll->staff_id)
                ->when($payroll->contract_id, fn ($query) => $query->where('contract_id', $payroll->contract_id))
                ->orderByDesc('id')
                ->first();

            $entry = AccountingJournalEntry::query()->create([
                'entry_number' => sprintf('REM-%s-%d', $payroll->period?->year . str_pad((string) $payroll->period?->month, 2, '0', STR_PAD_LEFT), $payroll->id),
                'entry_date' => $payroll->period?->period_end ?? now()->toDateString(),
                'status' => 'registrado',
                'description' => 'Centralización de remuneración ' . $payroll->code,
                'sourceable_type' => $payroll->getMorphClass(),
                'sourceable_id' => $payroll->id,
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ]);

            foreach ($payroll->distributions as $distribution) {
                AccountingJournalEntryLine::query()->create([
                    'journal_entry_id' => $entry->id,
                    'manual_account_id' => $setting?->accounting_debit_account_id,
                    'cost_center_id' => $distribution->cost_center_id,
                    'funding_source_id' => $distribution->funding_source_id,
                    'line_description' => 'Costo remuneración ' . $payroll->code,
                    'debit' => $distribution->total_cost_amount,
                    'credit' => 0,
                ]);
            }

            AccountingJournalEntryLine::query()->create([
                'journal_entry_id' => $entry->id,
                'manual_account_id' => $setting?->accounting_credit_account_id,
                'line_description' => 'Obligación remuneración ' . $payroll->code,
                'debit' => 0,
                'credit' => $payroll->total_cost,
            ]);

            $export = RemunerationAccountingExport::query()->create([
                'period_id' => $payroll->period_id,
                'payroll_id' => $payroll->id,
                'journal_entry_id' => $entry->id,
                'export_code' => 'CENT-' . $payroll->code,
                'status' => 'generado',
                'exported_at' => now(),
                'exported_by' => $user?->id,
                'total_debit' => $payroll->total_cost,
                'total_credit' => $payroll->total_cost,
                'payload' => [
                    'payroll_id' => $payroll->id,
                    'journal_entry_id' => $entry->id,
                    'distribution_count' => $payroll->distributions->count(),
                ],
            ]);

            $this->auditService->log(
                'centralizar',
                $payroll,
                $user,
                [],
                $payroll->fresh()?->getAttributes() ?? [],
                'Centralización contable de liquidación.',
                null,
                ['journal_entry_id' => $entry->id, 'export_id' => $export->id]
            );

            return $export->fresh(['journalEntry.lines', 'payroll']);
        });
    }
}
