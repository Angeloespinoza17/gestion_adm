<?php

namespace App\Models\Supply;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplyStoreroom extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = ['active' => 'boolean'];

    public function items(): HasMany
    {
        return $this->hasMany(SupplyItem::class, 'storeroom_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(SupplyReceipt::class, 'storeroom_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(SupplyDelivery::class, 'storeroom_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
