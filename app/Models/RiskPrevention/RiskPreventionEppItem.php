<?php

namespace App\Models\RiskPrevention;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiskPreventionEppItem extends Model
{
    protected $table = 'prevent_epp_items';

    protected $fillable = [
        'name',
        'epp_type',
        'stock',
        'minimum_stock',
        'unit',
        'description',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'stock' => 'integer',
        'minimum_stock' => 'integer',
        'active' => 'boolean',
    ];

    protected $appends = [
        'stock_status',
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(RiskPreventionEppDelivery::class, 'epp_item_id')->orderByDesc('delivered_at');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getStockStatusAttribute(): string
    {
        if ($this->stock <= 0) {
            return 'agotado';
        }

        if ($this->stock <= $this->minimum_stock) {
            return 'critico';
        }

        return 'disponible';
    }
}
