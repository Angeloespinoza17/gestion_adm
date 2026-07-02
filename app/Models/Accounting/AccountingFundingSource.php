<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingFundingSource extends AccountingModel
{
    use SoftDeletes;

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function budgetLines(): HasMany
    {
        return $this->hasMany(AccountingBudgetLine::class, 'funding_source_id');
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(AccountingIncome::class, 'funding_source_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(AccountingExpense::class, 'funding_source_id');
    }
}
