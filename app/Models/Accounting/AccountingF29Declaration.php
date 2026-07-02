<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingF29Declaration extends AccountingModel
{
    use SoftDeletes;

    protected $casts = [
        'vat_debit' => 'decimal:2',
        'vat_credit' => 'decimal:2',
        'ppm_amount' => 'decimal:2',
        'withholding_amount' => 'decimal:2',
        'other_taxes' => 'array',
        'filed_at' => 'date',
        'paid_at' => 'date',
    ];

    public function taxPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingTaxPeriod::class, 'tax_period_id');
    }
}
