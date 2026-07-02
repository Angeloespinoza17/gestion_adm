<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermissionRequestDocument extends Model
{
    use HasFactory;

    public const VALIDATION_STATUS_OPTIONS = [
        ['value' => 'pendiente', 'label' => 'Pendiente'],
        ['value' => 'validado', 'label' => 'Validado'],
        ['value' => 'rechazado', 'label' => 'Rechazado'],
    ];

    protected $fillable = [
        'permission_request_id',
        'uploaded_by_user_id',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'validated_by_user_id',
        'validated_at',
        'validation_status',
        'comments',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'validated_at' => 'datetime',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function permissionRequest(): BelongsTo
    {
        return $this->belongsTo(PermissionRequest::class);
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function validatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by_user_id');
    }
}
