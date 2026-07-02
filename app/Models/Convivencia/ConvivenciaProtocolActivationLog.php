<?php

namespace App\Models\Convivencia;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConvivenciaProtocolActivationLog extends Model
{
    use HasFactory;

    protected $table = 'convivencia_protocol_activation_logs';

    protected $fillable = [
        'activation_id',
        'protocol_step_id',
        'created_by',
        'action_type',
        'stage_name',
        'notes',
        'due_at',
        'completed_at',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function activation(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaProtocolActivation::class, 'activation_id');
    }

    public function protocolStep(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaProtocolStep::class, 'protocol_step_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
