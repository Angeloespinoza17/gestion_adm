<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingPayable extends AccountingModel
{
    use SoftDeletes;

    protected $casts = [
        'due_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(AccountingParty::class, 'party_id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(AccountingExpense::class, 'expense_id');
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
