<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingManualAccount extends AccountingModel
{
    use SoftDeletes;

    protected $casts = [
        'allows_movements' => 'boolean',
        'requires_evidence' => 'boolean',
        'requires_cost_center' => 'boolean',
        'requires_funding_source' => 'boolean',
        'is_active' => 'boolean',
        'level' => 'integer',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(AccountingManualVersion::class, 'manual_version_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }
}
