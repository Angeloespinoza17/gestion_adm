<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PorterAuthorizationRequest extends Model
{
    use HasFactory;

    public const STATUS_OPTIONS = [
        ['value' => 'pendiente', 'label' => 'Pendiente'],
        ['value' => 'aprobada', 'label' => 'Aprobada'],
        ['value' => 'rechazada', 'label' => 'Rechazada'],
        ['value' => 'observada', 'label' => 'Observada'],
        ['value' => 'anulada', 'label' => 'Anulada'],
    ];

    protected $fillable = [
        'requested_by',
        'resolved_by',
        'status',
        'required_permission_slug',
        'reason',
        'requested_at',
        'resolved_at',
        'resolution_notes',
        'payload',
    ];

    protected $casts = [
        'requested_at' => 'datetime:Y-m-d H:i:s',
        'resolved_at' => 'datetime:Y-m-d H:i:s',
        'payload' => 'array',
    ];

    public function authorizable(): MorphTo
    {
        return $this->morphTo();
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
