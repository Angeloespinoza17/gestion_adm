<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermissionRequestLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'permission_request_id',
        'user_id',
        'action',
        'old_status',
        'new_status',
        'details',
        'created_at',
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public function permissionRequest(): BelongsTo
    {
        return $this->belongsTo(PermissionRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
