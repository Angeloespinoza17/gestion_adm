<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingParty extends AccountingModel
{
    use SoftDeletes;

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function incomes(): HasMany
    {
        return $this->hasMany(AccountingIncome::class, 'party_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(AccountingExpense::class, 'party_id');
    }

    public function payables(): HasMany
    {
        return $this->hasMany(AccountingPayable::class, 'party_id');
    }
}
