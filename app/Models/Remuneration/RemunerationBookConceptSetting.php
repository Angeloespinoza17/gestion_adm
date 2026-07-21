<?php

namespace App\Models\Remuneration;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemunerationBookConceptSetting extends RemunerationModel
{
    protected $casts = [
        'is_union_income' => 'boolean',
        'last_seen_at' => 'datetime:Y-m-d H:i',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
