<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_item_id',
        'from_dependency_id',
        'to_dependency_id',
        'from_user_id',
        'to_user_id',
        'movement_type',
        'movement_date',
        'reason',
        'observations',
        'created_by',
    ];

    protected $casts = [
        'movement_date' => 'date',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function fromDependency(): BelongsTo
    {
        return $this->belongsTo(MaintenanceDependency::class, 'from_dependency_id');
    }

    public function toDependency(): BelongsTo
    {
        return $this->belongsTo(MaintenanceDependency::class, 'to_dependency_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

