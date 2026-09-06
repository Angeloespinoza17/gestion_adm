<?php

namespace App\Models\Convivencia;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ConvivenciaProtocolActivation extends Model
{
    use HasFactory;

    protected $table = 'convivencia_protocol_activations';

    public const STATUS_OPTIONS = [
        ['value' => 'activo', 'label' => 'Activo'],
        ['value' => 'en_seguimiento', 'label' => 'En seguimiento'],
        ['value' => 'cerrado', 'label' => 'Cerrado'],
        ['value' => 'vencido', 'label' => 'Vencido'],
    ];

    protected $fillable = [
        'protocol_id',
        'case_id',
        'complaint_id',
        'current_step_id',
        'current_activation_step_id',
        'activated_by',
        'activated_at',
        'status',
        'current_stage_name',
        'due_at',
        'involved_snapshot',
        'protocol_snapshot',
        'progress_percentage',
        'revision',
        'actions_taken',
        'measures_adopted',
        'closing_summary',
        'closed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'due_at' => 'datetime',
        'closed_at' => 'datetime',
        'involved_snapshot' => 'array',
        'protocol_snapshot' => 'array',
        'progress_percentage' => 'float',
        'revision' => 'integer',
    ];

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaProtocol::class, 'protocol_id')->withTrashed();
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaCase::class, 'case_id');
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaComplaint::class, 'complaint_id');
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaProtocolStep::class, 'current_step_id');
    }

    public function currentActivationStep(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaProtocolActivationStep::class, 'current_activation_step_id');
    }

    public function runtimeSteps(): HasMany
    {
        return $this->hasMany(ConvivenciaProtocolActivationStep::class, 'activation_id')
            ->orderBy('step_order')
            ->orderBy('id');
    }

    public function runtimeParts(): HasMany
    {
        return $this->hasMany(ConvivenciaProtocolActivationPart::class, 'activation_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ConvivenciaProtocolActivationLog::class, 'activation_id')->latest('created_at');
    }

    public function statusLogs(): MorphMany
    {
        return $this->morphMany(ConvivenciaStatusLog::class, 'loggable')->latest('changed_at')->latest('id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(ConvivenciaAttachment::class, 'attachable')->latest('created_at');
    }
}
