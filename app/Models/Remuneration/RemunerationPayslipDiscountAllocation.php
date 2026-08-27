<?php

namespace App\Models\Remuneration;

use App\Models\Accounting\AccountingFundingSource;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemunerationPayslipDiscountAllocation extends RemunerationModel
{
    protected $casts = [
        'base_amount' => 'integer',
        'proportion' => 'decimal:12',
        'calculated_amount' => 'decimal:6',
        'rounding_adjustment' => 'integer',
        'assigned_amount' => 'integer',
    ];

    public function discount(): BelongsTo
    {
        return $this->belongsTo(RemunerationPayslipDiscount::class, 'discount_id');
    }

    public function fundingSource(): BelongsTo
    {
        return $this->belongsTo(AccountingFundingSource::class, 'funding_source_id');
    }
}
