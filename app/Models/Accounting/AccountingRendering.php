<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingRendering extends AccountingModel
{
    use SoftDeletes;

    protected $casts = [
        'reviewed_at' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(AccountingRenderingItem::class, 'rendering_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewed_by');
    }
}
