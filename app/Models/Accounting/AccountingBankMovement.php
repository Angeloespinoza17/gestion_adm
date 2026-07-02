<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AccountingBankMovement extends AccountingModel
{
    protected $casts = [
        'movement_date' => 'date',
        'amount' => 'decimal:2',
        'is_reconciled' => 'boolean',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingBankAccount::class, 'bank_account_id');
    }

    public function referenceable(): MorphTo
    {
        return $this->morphTo();
    }
}
