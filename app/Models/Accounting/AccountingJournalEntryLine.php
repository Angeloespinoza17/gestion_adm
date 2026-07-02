<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingJournalEntryLine extends AccountingModel
{
    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(AccountingJournalEntry::class, 'journal_entry_id');
    }

    public function manualAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingManualAccount::class, 'manual_account_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(AccountingCostCenter::class, 'cost_center_id');
    }

    public function fundingSource(): BelongsTo
    {
        return $this->belongsTo(AccountingFundingSource::class, 'funding_source_id');
    }
}
