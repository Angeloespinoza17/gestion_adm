<?php

namespace App\Models\Remuneration;

use Illuminate\Database\Eloquent\SoftDeletes;

class RemunerationLegalParameter extends RemunerationModel
{
    use SoftDeletes;

    protected $casts = [
        'value' => 'decimal:6',
        'effective_from' => 'date:Y-m-d',
        'effective_until' => 'date:Y-m-d',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];
}
