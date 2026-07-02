<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PorterKeyLoan extends Model
{
    use HasFactory;

    public const STATUS_OPTIONS = [
        ['value' => 'prestada', 'label' => 'Prestada'],
        ['value' => 'devuelta', 'label' => 'Devuelta'],
        ['value' => 'observada', 'label' => 'Observada'],
    ];

    protected $fillable = [
        'porter_key_id',
        'staff_id',
        'maintenance_dependency_id',
        'registered_by',
        'returned_to_by',
        'status',
        'checked_out_at',
        'expected_return_at',
        'returned_at',
        'requester_name',
        'requester_rut',
        'purpose',
        'observations',
        'return_observations',
        'metadata',
    ];

    protected $casts = [
        'checked_out_at' => 'datetime:Y-m-d H:i:s',
        'expected_return_at' => 'datetime:Y-m-d H:i:s',
        'returned_at' => 'datetime:Y-m-d H:i:s',
        'metadata' => 'array',
    ];

    public function porterKey(): BelongsTo
    {
        return $this->belongsTo(PorterKey::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function dependency(): BelongsTo
    {
        return $this->belongsTo(MaintenanceDependency::class, 'maintenance_dependency_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function returnedToBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_to_by');
    }

    public function logs(): MorphMany
    {
        return $this->morphMany(PorterMovementLog::class, 'loggable')->latest('performed_at')->latest('id');
    }
}
