<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingCashFund extends AccountingModel
{
    use SoftDeletes;

    protected $casts = [
        'initial_amount' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'delivered_at' => 'date',
        'due_at' => 'date',
    ];

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'responsible_user_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(AccountingCostCenter::class, 'cost_center_id');
    }

    public function fundingSource(): BelongsTo
    {
        return $this->belongsTo(AccountingFundingSource::class, 'funding_source_id');
    }
}
