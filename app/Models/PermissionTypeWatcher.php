<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermissionTypeWatcher extends Model
{
    use HasFactory;

    public const TARGET_OPTIONS = [
        ['value' => 'manager', 'label' => 'Jefatura directa'],
        ['value' => 'direction', 'label' => 'Dirección'],
        ['value' => 'hr', 'label' => 'RRHH / Administración'],
        ['value' => 'role', 'label' => 'Rol'],
        ['value' => 'user', 'label' => 'Usuario específico'],
    ];

    protected $fillable = [
        'permission_type_id',
        'target_type',
        'role_id',
        'user_id',
        'notify',
        'can_view',
        'active',
    ];

    protected $casts = [
        'notify' => 'boolean',
        'can_view' => 'boolean',
        'active' => 'boolean',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function permissionType(): BelongsTo
    {
        return $this->belongsTo(PermissionType::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requestWatchers(): HasMany
    {
        return $this->hasMany(PermissionRequestWatcher::class);
    }
}
