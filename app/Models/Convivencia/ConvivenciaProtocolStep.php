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
        'stage_name',
        'responsible_label',
        'due_days',
        'required_documents',
        'minimal_actions',
        'safeguard_measures',
    ];

    protected $casts = [
        'step_order' => 'integer',
        'due_days' => 'integer',
    ];

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaProtocol::class, 'protocol_id');
    }

    public function activationLogs(): HasMany
    {
        return $this->hasMany(ConvivenciaProtocolActivationLog::class, 'protocol_step_id')->latest('created_at');
    }
}
