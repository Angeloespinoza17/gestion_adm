<?php

namespace App\Models\Convivencia;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConvivenciaProtocolStep extends Model
{
    use HasFactory;

    protected $table = 'convivencia_protocol_steps';

    protected $fillable = [
        'protocol_id',
        'step_order',
        'code',
        'stage_name',
        'description',
        'step_type',
        'responsible_label',
        'due_days',
        'deadline_value',
        'deadline_unit',
        'deadline_anchor',
        'can_extend',
        'extension_value',
        'extension_unit',
        'completion_rule',
        'active',
        'metadata',
        'required_documents',
        'minimal_actions',
        'safeguard_measures',
    ];

    protected $casts = [
        'step_order' => 'integer',
        'due_days' => 'integer',
        'deadline_value' => 'integer',
        'can_extend' => 'boolean',
        'extension_value' => 'integer',
        'completion_rule' => 'array',
        'active' => 'boolean',
        'metadata' => 'array',
    ];

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaProtocol::class, 'protocol_id');
    }

    public function activationLogs(): HasMany
    {
        return $this->hasMany(ConvivenciaProtocolActivationLog::class, 'protocol_step_id')->latest('created_at');
    }

    public function partLinks(): HasMany
    {
        return $this->hasMany(ConvivenciaProtocolPartLink::class, 'protocol_step_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function activationSteps(): HasMany
    {
        return $this->hasMany(ConvivenciaProtocolActivationStep::class, 'source_protocol_step_id');
    }
}
