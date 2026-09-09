<?php

namespace App\Models\Supply;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplyDeliveryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'supply_delivery_id', 'supply_item_id', 'quantity', 'item_name_snapshot',
        'unit_snapshot', 'previous_stock', 'new_stock', 'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'previous_stock' => 'decimal:2',
        'new_stock' => 'decimal:2',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(SupplyDelivery::class, 'supply_delivery_id');
    }

    public function supplyItem(): BelongsTo
    {
        return $this->belongsTo(SupplyItem::class)->withTrashed();
    }
}
