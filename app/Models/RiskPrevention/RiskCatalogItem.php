<?php

namespace App\Models\RiskPrevention;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiskCatalogItem extends Model
{
    protected $table = 'prevent_risk_catalog_items';

    protected $guarded = [];

    protected $casts = ['configuration' => 'array', 'active' => 'boolean', 'valid_from' => 'date', 'valid_until' => 'date'];

    public function methodology(): BelongsTo
    {
        return $this->belongsTo(RiskMethodology::class, 'methodology_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('catalog_type', $type);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('active', true)
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()));
    }
}
