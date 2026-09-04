<?php

namespace App\Models\Supply;

use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplyItem extends Model
{
    use HasFactory;

    public const SECTION_CLEANING = 'cleaning';

    public const SECTION_HEATING = 'heating';

    public const SECTION_MAINTENANCE_STOREROOM = 'maintenance_storeroom';

    public static function sections(): array
    {
        return [
            self::SECTION_CLEANING,
            self::SECTION_HEATING,
            self::SECTION_MAINTENANCE_STOREROOM,
        ];
    }

    protected $fillable = [
        'inventory_item_id',
        'section',
        'storeroom_id',
        'supply_type',
        'reference_photo_path',
    ];

    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute(): ?string
    {
        if ($this->reference_photo_path) {
            $version = $this->updated_at?->timestamp ?: time();

            return "/api/supplies/items/{$this->id}/photo?v={$version}";
        }

        $inventoryItem = $this->relationLoaded('inventoryItem') ? $this->inventoryItem : null;
        if ($inventoryItem?->image_path) {
            $version = $inventoryItem->updated_at?->timestamp ?: time();

            return "/api/supplies/inventory-items/{$inventoryItem->id}/image?v={$version}";
        }

        return null;
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function storeroom(): BelongsTo
    {
        return $this->belongsTo(SupplyStoreroom::class, 'storeroom_id');
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(SupplyReceiptItem::class);
    }

    public function deliveryItems(): HasMany
    {
        return $this->hasMany(SupplyDeliveryItem::class);
    }

    public function requestItems(): HasMany
    {
        return $this->hasMany(SupplyRequestItem::class);
    }
}
