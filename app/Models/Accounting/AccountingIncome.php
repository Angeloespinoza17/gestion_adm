<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingIncome extends AccountingModel
{
    use SoftDeletes;

    protected $casts = [
        'received_at' => 'date',
        'amount' => 'decimal:2',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(AccountingParty::class, 'party_id');
    }

    public function fundingSource(): BelongsTo
    {
        return $this->belongsTo(AccountingFundingSource::class, 'funding_source_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(AccountingCostCenter::class, 'cost_center_id');
    }

    public function manualAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingManualAccount::class, 'manual_account_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingBankAccount::class, 'bank_account_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(AccountingDocument::class, 'documentable');
    }
}
