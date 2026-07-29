<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingSubsidySettlement extends AccountingModel
{
    use SoftDeletes;

    protected $casts = [
        'period' => 'date',
        'payment_date' => 'date',
        'gross_amount' => 'decimal:2',
        'adjustments_amount' => 'decimal:2',
        'deductions_amount' => 'decimal:2',
        'reliquidations_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'transferred_amount' => 'decimal:2',
        'difference_amount' => 'decimal:2',
        'metadata' => 'array',
        'approved_at' => 'datetime',
    ];

    public function fundingSource(): BelongsTo
    {
        return $this->belongsTo(AccountingFundingSource::class, 'funding_source_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingSubsidySettlementLine::class, 'settlement_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(AccountingSubsidyAllocation::class, 'settlement_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(AccountingSubsidyMatch::class, 'settlement_id');
    }
}
