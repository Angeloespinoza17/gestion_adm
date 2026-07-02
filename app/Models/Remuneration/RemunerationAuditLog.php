<?php

namespace App\Models\Remuneration;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RemunerationAuditLog extends RemunerationModel
{
    public $timestamps = false;

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changes' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime:Y-m-d H:i',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
