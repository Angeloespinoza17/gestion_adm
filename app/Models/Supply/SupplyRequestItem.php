<?php

namespace App\Models\Supply;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplyRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'supply_request_id', 'supply_item_id', 'item_name_snapshot', 'description_snapshot',
        'unit_snapshot', 'requested_quantity', 'final_quantity', 'reference_photo_path', 'sort_order',
    ];

    protected $hidden = ['reference_photo_path'];

    protected $casts = [
        'requested_quantity' => 'decimal:2',
        'final_quantity' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->reference_photo_path && ! $this->supplyItem?->reference_photo_path) {
            return null;
        }

        $version = $this->updated_at?->timestamp ?: time();

        return "/api/supplies/request-items/{$this->id}/photo?v={$version}";
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class, 'supply_request_id');
    }

    public function supplyItem(): BelongsTo
    {
        return $this->belongsTo(SupplyItem::class);
    }
}
