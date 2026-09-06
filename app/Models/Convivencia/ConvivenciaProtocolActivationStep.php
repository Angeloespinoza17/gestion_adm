<?php

namespace App\Models\Convivencia;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConvivenciaProtocolActivationStep extends Model
{
    use HasFactory;

    public const STATUS_OPTIONS = ['pending', 'in_progress', 'completed', 'blocked', 'skipped'];

    protected $table = 'convivencia_protocol_activation_steps';

    protected $fillable = [
        'activation_id',
        'source_protocol_step_id',
        'step_order',
        'code',
        'stage_name',
        'description',
        'step_type',
        'responsible_label',
        'status',
        'deadline_value',
        'deadline_unit',
        'deadline_anchor',
        'can_extend',
        'extension_value',
        'extension_unit',
        'started_at',
        'due_at',
        'completed_at',
        'completed_by',
        'notes',
        'outcome',
        'evidence_summary',
        'data',
        'snapshot',
    ];

    protected $casts = [
        'step_order' => 'integer',
        'deadline_value' => 'integer',
        'can_extend' => 'boolean',
        'extension_value' => 'integer',
        'started_at' => 'datetime',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'data' => 'array',
        'snapshot' => 'array',
    ];

    public function activation(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaProtocolActivation::class, 'activation_id');
    }

    public function sourceStep(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaProtocolStep::class, 'source_protocol_step_id');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(ConvivenciaProtocolActivationPart::class, 'activation_step_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
