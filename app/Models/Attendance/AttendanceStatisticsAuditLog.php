<?php

namespace App\Models\Attendance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AttendanceStatisticsAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'auditable_type', 'auditable_id', 'action', 'origin', 'ip_address',
        'old_values', 'new_values', 'changes', 'reason', 'metadata', 'created_at',
    ];

    protected $casts = [
        'old_values' => 'array', 'new_values' => 'array', 'changes' => 'array',
        'metadata' => 'array', 'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
