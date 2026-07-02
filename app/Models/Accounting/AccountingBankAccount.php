<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingBankAccount extends AccountingModel
{
    use SoftDeletes;

    protected $casts = [
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function movements(): HasMany
    {
        return $this->hasMany(AccountingBankMovement::class, 'bank_account_id')->latest('movement_date');
    }

    public function cheques(): HasMany
    {
        return $this->hasMany(AccountingCheque::class, 'bank_account_id');
    }
}
