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

    protected $hidden = [
        'document_path',
    ];

    protected $fillable = [
        'document_type',
        'title',
        'document_group',
        'version_number',
        'valid_from',
        'valid_until',
        'status',
        'is_disseminable',
        'responsible_name',
        'document_path',
        'document_name',
        'mime_type',
        'file_extension',
        'file_size',
        'disseminated_at',
        'disseminated_by',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_disseminable' => 'boolean',
        'file_size' => 'integer',
        'disseminated_at' => 'datetime',
    ];

    protected $appends = [
        'days_until_expiration',
        'current_status',
        'has_file',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function disseminatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disseminated_by');
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

    public function getHasFileAttribute(): bool
    {
        return filled($this->document_path);
    }
}
