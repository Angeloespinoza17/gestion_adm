<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingCostCenter extends AccountingModel
{
    use SoftDeletes;

    protected $casts = [
        'is_active' => 'boolean',
        'valid_year' => 'integer',
    ];

    public function budgetLines(): HasMany
    {
        return $this->hasMany(AccountingBudgetLine::class, 'cost_center_id');
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(AccountingIncome::class, 'cost_center_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(AccountingExpense::class, 'cost_center_id');
    }
}
