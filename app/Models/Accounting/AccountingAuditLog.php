<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AccountingAuditLog extends AccountingModel
{
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
