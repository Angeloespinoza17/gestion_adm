<?php

namespace App\Models\RiskPrevention;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskPreventionFireExtinguisher extends Model
{
    public const STATUS_VIGENTE = 'vigente';
    public const STATUS_POR_VENCER = 'por_vencer';
    public const STATUS_VENCIDO = 'vencido';
    public const STATUS_DADO_BAJA = 'dado_baja';

    protected $table = 'prevent_fire_extinguishers';

    protected $fillable = [
        'code',
        'extinguisher_type',
        'building',
        'floor',
        'dependency_name',
        'installed_at',
        'expires_at',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'installed_at' => 'date',
        'expires_at' => 'date',
    ];

    protected $appends = [
        'location_label',
        'days_until_expiration',
        'alert_level',
        'current_status',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getLocationLabelAttribute(): string
    {
        return collect([$this->building, $this->floor, $this->dependency_name])
            ->filter(fn ($value) => filled($value))
            ->implode(' / ');
    }

    public function getDaysUntilExpirationAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        return now()->startOfDay()->diffInDays($this->expires_at->copy()->startOfDay(), false);
    }

    public function getAlertLevelAttribute(): ?int
    {
        if ($this->status === self::STATUS_DADO_BAJA || $this->days_until_expiration === null) {
            return null;
        }

        if ($this->days_until_expiration <= 7) {
            return 7;
        }

        if ($this->days_until_expiration <= 15) {
            return 15;
        }

        if ($this->days_until_expiration <= 30) {
            return 30;
        }

        return null;
    }

    public function getCurrentStatusAttribute(): string
    {
        if ($this->status === self::STATUS_DADO_BAJA) {
            return self::STATUS_DADO_BAJA;
        }

        if ($this->days_until_expiration !== null && $this->days_until_expiration < 0) {
            return self::STATUS_VENCIDO;
        }

        if ($this->days_until_expiration !== null && $this->days_until_expiration <= 30) {
            return self::STATUS_POR_VENCER;
        }

        return self::STATUS_VIGENTE;
    }
}
