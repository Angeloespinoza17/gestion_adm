<?php

namespace App\Models\RiskPrevention;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskPreventionDocument extends Model
{
    public const STATUS_VIGENTE = 'vigente';
    public const STATUS_POR_VENCER = 'por_vencer';
    public const STATUS_VENCIDO = 'vencido';
    public const STATUS_ARCHIVADO = 'archivado';

    protected $table = 'prevent_documents';

    protected $fillable = [
        'document_type',
        'title',
        'document_group',
        'version_number',
        'valid_from',
        'valid_until',
        'status',
        'responsible_name',
        'document_path',
        'document_name',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    protected $appends = [
        'days_until_expiration',
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

    public function getDaysUntilExpirationAttribute(): ?int
    {
        if (!$this->valid_until) {
            return null;
        }

        return now()->startOfDay()->diffInDays($this->valid_until->copy()->startOfDay(), false);
    }

    public function getCurrentStatusAttribute(): string
    {
        if ($this->status === self::STATUS_ARCHIVADO) {
            return self::STATUS_ARCHIVADO;
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
