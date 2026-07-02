<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingBudget extends AccountingModel
{
    use SoftDeletes;

    protected $casts = [
        'year' => 'integer',
        'approved_at' => 'date',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingBudgetLine::class, 'budget_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }
}
