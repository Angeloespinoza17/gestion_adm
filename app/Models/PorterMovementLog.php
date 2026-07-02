<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PorterMovementLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'performed_by',
        'action',
        'from_status',
        'to_status',
        'description',
        'performed_at',
        'ip_address',
        'user_agent',
        'payload',
    ];

    protected $casts = [
        'performed_at' => 'datetime:Y-m-d H:i:s',
        'payload' => 'array',
    ];

    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
