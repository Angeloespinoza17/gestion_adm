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
        'activated_by',
        'activated_at',
        'status',
        'current_stage_name',
        'due_at',
        'involved_snapshot',
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
    ];

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaProtocol::class, 'protocol_id');
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
}
