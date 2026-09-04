<?php

namespace App\Models\Supply;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplyReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'folio', 'section', 'storeroom_id', 'purchased_at', 'supplier_id', 'document_type',
        'document_number', 'total_amount', 'notes', 'received_by', 'created_by',
    ];

    protected $casts = [
        'purchased_at' => 'date',
        'total_amount' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SupplyReceiptItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function storeroom(): BelongsTo
    {
        return $this->belongsTo(SupplyStoreroom::class, 'storeroom_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
