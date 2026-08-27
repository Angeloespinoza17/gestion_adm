<?php

namespace App\Models\RiskPrevention;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiskPreventionEppItem extends Model
{
    protected $table = 'prevent_epp_items';

    protected $fillable = [
        'name',
        'inventory_item_id',
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
        'available_stock',
        'available_minimum_stock',
        'available_unit',
        'stock_source',
        'stock_status',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

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
        if ($this->available_stock <= 0) {
            return 'agotado';
        }

        if ($this->available_stock <= $this->available_minimum_stock) {
            return 'critico';
        }

        return 'disponible';
    }

    public function getAvailableStockAttribute(): float|int
    {
        if ($this->inventory_item_id) {
            return (float) ($this->inventoryItem?->stock_quantity ?? 0);
        }

        return (int) $this->stock;
    }

    public function getAvailableMinimumStockAttribute(): float|int
    {
        if ($this->inventory_item_id) {
            return (float) ($this->inventoryItem?->minimum_stock ?? 0);
        }

        return (int) $this->minimum_stock;
    }

    public function getAvailableUnitAttribute(): string
    {
        if ($this->inventory_item_id) {
            return (string) ($this->inventoryItem?->unit_of_measure ?: $this->unit);
        }

        return (string) $this->unit;
    }

    public function getStockSourceAttribute(): string
    {
        return $this->inventory_item_id ? 'bodega' : 'legacy';
    }
}
