<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingRenderingItem extends AccountingModel
{
    protected $casts = [
        'amount' => 'decimal:2',
        'rendered_at' => 'date',
    ];

    public function rendering(): BelongsTo
    {
        return $this->belongsTo(AccountingRendering::class, 'rendering_id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(AccountingExpense::class, 'expense_id');
    }

    public function income(): BelongsTo
    {
        return $this->belongsTo(AccountingIncome::class, 'income_id');
    }
}
