<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingSubsidyImport extends AccountingModel
{
    protected $casts = [
        'period' => 'date',
        'summary' => 'array',
        'warnings' => 'array',
        'errors' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingSubsidySettlementLine::class, 'import_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
