<?php

namespace App\Models\Convivencia;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConvivenciaProtocolActivationPart extends Model
{
    use HasFactory;

    public const STATUS_OPTIONS = ['pending', 'in_progress', 'completed', 'not_applicable'];

    protected $table = 'convivencia_protocol_activation_parts';

    protected $fillable = [
        'activation_id',
        'activation_step_id',
        'source_link_id',
        'protocol_part_id',
        'category',
        'code',
        'title',
        'description',
        'instructions',
        'responsible_label',
        'population_scope',
        'legal_reference',
        'sort_order',
        'is_required',
        'requires_evidence',
        'status',
        'started_at',
        'due_at',
        'completed_at',
        'completed_by',
        'notes',
        'evidence_summary',
        'outcome',
        'data',
        'snapshot',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_required' => 'boolean',
        'requires_evidence' => 'boolean',
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

    public function activationStep(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaProtocolActivationStep::class, 'activation_step_id');
    }

    public function sourceLink(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaProtocolPartLink::class, 'source_link_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaProtocolPart::class, 'protocol_part_id')->withTrashed();
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
