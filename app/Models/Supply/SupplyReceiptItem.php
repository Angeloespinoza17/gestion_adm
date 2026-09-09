<?php

namespace App\Models\Supply;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplyReceiptItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'supply_receipt_id', 'supply_item_id', 'quantity', 'unit_cost',
        'unit_snapshot', 'previous_stock', 'new_stock',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'integer',
        'previous_stock' => 'decimal:2',
        'new_stock' => 'decimal:2',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(SupplyReceipt::class, 'supply_receipt_id');
    }

    public function supplyItem(): BelongsTo
    {
        return $this->belongsTo(SupplyItem::class)->withTrashed();
    }
}
