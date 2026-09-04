<?php

namespace App\Models\Supply;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplyDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'folio', 'section', 'storeroom_id', 'delivered_at', 'recipient_staff_id', 'recipient_name', 'recipient_rut',
        'recipient_role', 'destination', 'notes', 'delivered_by', 'created_by',
    ];

    protected $casts = ['delivered_at' => 'date'];

    public function items(): HasMany
    {
        return $this->hasMany(SupplyDeliveryItem::class);
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function storeroom(): BelongsTo
    {
        return $this->belongsTo(SupplyStoreroom::class, 'storeroom_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'recipient_staff_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
