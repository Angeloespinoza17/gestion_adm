<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AccountingDocument extends AccountingModel
{
    protected $casts = [
        'metadata' => 'array',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by');
    }
}
