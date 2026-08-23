<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingBudgetExecutionImport extends AccountingModel
{
    protected $casts = [
        'metadata' => 'array',
        'imported_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingBudgetExecutionLine::class, 'import_id');
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
