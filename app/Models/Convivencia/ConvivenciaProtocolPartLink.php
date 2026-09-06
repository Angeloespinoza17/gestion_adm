<?php

namespace App\Models\Convivencia;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConvivenciaProtocolPartLink extends Model
{
    use HasFactory;

    protected $table = 'convivencia_protocol_part_links';

    protected $fillable = [
        'protocol_id',
        'protocol_step_id',
        'protocol_part_id',
        'sort_order',
        'is_required',
        'condition',
        'configuration',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_required' => 'boolean',
        'condition' => 'array',
        'configuration' => 'array',
    ];

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaProtocol::class, 'protocol_id')->withTrashed();
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaProtocolStep::class, 'protocol_step_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaProtocolPart::class, 'protocol_part_id')->withTrashed();
    }

    public function activationParts(): HasMany
    {
        return $this->hasMany(ConvivenciaProtocolActivationPart::class, 'source_link_id');
    }
}
