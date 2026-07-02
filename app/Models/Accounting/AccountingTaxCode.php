<?php

namespace App\Models\Accounting;

class AccountingTaxCode extends AccountingModel
{
    protected $casts = [
        'year' => 'integer',
        'is_active' => 'boolean',
    ];
}
