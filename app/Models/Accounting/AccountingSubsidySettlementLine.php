<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingSubsidySettlementLine extends AccountingModel
{
    protected $casts = [
        'amount' => 'decimal:2',
        'declared_amount' => 'decimal:2',
        'education_allocable' => 'boolean',
        'informative' => 'boolean',
        'metadata' => 'array',
    ];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(AccountingSubsidySettlement::class, 'settlement_id');
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(AccountingSubsidyImport::class, 'import_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(AccountingSubsidyAllocation::class, 'line_id');
    }
}
