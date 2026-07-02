<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermissionRequestWatcher extends Model
{
    use HasFactory;

    protected $fillable = [
        'permission_request_id',
        'user_id',
        'permission_type_watcher_id',
        'staff_permission_watcher_id',
        'source_type',
        'source_label',
        'notify',
        'can_view',
        'notified_at',
    ];

    protected $casts = [
        'notify' => 'boolean',
        'can_view' => 'boolean',
        'notified_at' => 'datetime',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function permissionRequest(): BelongsTo
    {
        return $this->belongsTo(PermissionRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function permissionTypeWatcher(): BelongsTo
    {
        return $this->belongsTo(PermissionTypeWatcher::class);
    }

    public function staffPermissionWatcher(): BelongsTo
    {
        return $this->belongsTo(StaffPermissionWatcher::class);
    }
}
