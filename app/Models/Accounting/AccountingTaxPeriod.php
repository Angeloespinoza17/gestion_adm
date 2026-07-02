<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\HasOne;

class AccountingTaxPeriod extends AccountingModel
{
    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'filed_at' => 'date',
    ];

    public function f29Declaration(): HasOne
    {
        return $this->hasOne(AccountingF29Declaration::class, 'tax_period_id');
    }
}
