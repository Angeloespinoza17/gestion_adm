<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermissionRequestApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'permission_request_id',
        'approver_user_id',
        'role_or_step',
        'status',
        'comments',
        'internal_comments',
        'acted_at',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function permissionRequest(): BelongsTo
    {
        return $this->belongsTo(PermissionRequest::class);
    }

    public function approverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
