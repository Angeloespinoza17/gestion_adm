<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingSubsidyMatch extends AccountingModel
{
    protected $casts = [
        'matched_amount' => 'decimal:2',
        'matched_at' => 'datetime',
    ];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(AccountingSubsidySettlement::class, 'settlement_id');
    }

    public function income(): BelongsTo
    {
        return $this->belongsTo(AccountingIncome::class, 'income_id');
    }

    public function bankMovement(): BelongsTo
    {
        return $this->belongsTo(AccountingBankMovement::class, 'bank_movement_id');
    }
}
