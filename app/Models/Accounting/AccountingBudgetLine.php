<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingBudgetLine extends AccountingModel
{
    protected $casts = [
        'month' => 'integer',
        'planned_amount' => 'decimal:2',
        'executed_amount' => 'decimal:2',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(AccountingBudget::class, 'budget_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(AccountingCostCenter::class, 'cost_center_id');
    }

    public function fundingSource(): BelongsTo
    {
        return $this->belongsTo(AccountingFundingSource::class, 'funding_source_id');
    }

    public function manualAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingManualAccount::class, 'manual_account_id');
    }
}
