<?php

namespace App\Models;

use App\Models\Security\SecurityIncident;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class InventoryItem extends Model
{
    use HasFactory;

    protected $appends = [
        'image_url',
    ];

    protected $fillable = [
        'code',
        'qr_code',
        'barcode',
        'name',
        'description',
        'category_id',
        'subcategory_id',
        'brand',
        'model',
        'serial_number',
        'purchase_date',
        'purchase_value',
        'useful_life_years',
        'status',
        'condition',
        'dependency_id',
        'responsible_user_id',
        'supplier_id',
        'image_path',
        'active',
        'item_type',
        'stock_quantity',
        'minimum_stock',
        'unit_of_measure',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'active' => 'boolean',
        'purchase_value' => 'integer',
        'useful_life_years' => 'integer',
        'stock_quantity' => 'decimal:2',
        'minimum_stock' => 'decimal:2',
    ];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        $url = Storage::disk('public')->url($this->image_path);
        $parts = parse_url((string) $url);
        if (is_array($parts) && isset($parts['path'])) {
            return $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }

        return $url;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(InventorySubcategory::class, 'subcategory_id');
    }

    public function dependency(): BelongsTo
    {
        return $this->belongsTo(MaintenanceDependency::class, 'dependency_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(InventoryPhoto::class, 'inventory_item_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(InventoryDocument::class, 'inventory_item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'inventory_item_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(InventoryStockMovement::class, 'inventory_item_id');
    }

    public function maintenanceWorkOrders(): HasMany
    {
        return $this->hasMany(MaintenanceWorkOrder::class, 'inventory_item_id');
    }

    public function securityIncidents(): HasMany
    {
        return $this->hasMany(SecurityIncident::class, 'inventory_item_id');
    }
}
