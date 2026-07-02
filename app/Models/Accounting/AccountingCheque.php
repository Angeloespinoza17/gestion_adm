<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingCheque extends AccountingModel
{
    use SoftDeletes;

    protected $casts = [
        'amount' => 'decimal:2',
        'issued_at' => 'date',
        'cashed_at' => 'date',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingBankAccount::class, 'bank_account_id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(AccountingExpense::class, 'expense_id');
    }

    public function payable(): BelongsTo
    {
        return $this->belongsTo(AccountingPayable::class, 'payable_id');
    }
}
