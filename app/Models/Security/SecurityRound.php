<?php

namespace App\Models\Security;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SecurityRound extends Model
{
    use HasFactory;

    public const STATUS_SIN_NOVEDAD = 'sin_novedad';
    public const STATUS_OBSERVADO = 'observado';
    public const STATUS_REQUIERE_ATENCION = 'requiere_atencion';

    public const STATUS_OPTIONS = [
        ['value' => self::STATUS_SIN_NOVEDAD, 'label' => 'Sin novedad'],
        ['value' => self::STATUS_OBSERVADO, 'label' => 'Observado'],
        ['value' => self::STATUS_REQUIERE_ATENCION, 'label' => 'Requiere atención'],
    ];

    protected $fillable = [
        'security_shift_id',
        'recorded_by_user_id',
        'round_number',
        'recorded_at',
        'overall_status',
        'observations',
        'nochero_confirmation_name',
        'signature_data',
        'latitude',
        'longitude',
        'location_accuracy',
        'act_number',
        'act_generated_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'act_generated_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'location_accuracy' => 'decimal:2',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(SecurityShift::class, 'security_shift_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function sectors(): HasMany
    {
        return $this->hasMany(SecurityRoundSector::class)->orderBy('display_order')->orderBy('id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(SecurityIncident::class)->latest('id');
    }

    public function evidences(): MorphMany
    {
        return $this->morphMany(SecurityEvidence::class, 'attachable')->latest('id');
    }
}
