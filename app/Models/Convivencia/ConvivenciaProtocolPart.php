<?php

namespace App\Models\Convivencia;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConvivenciaProtocolPart extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const CATEGORY_OPTIONS = [
        'action',
        'sanction',
        'protective_measure',
        'formative_measure',
        'restorative_measure',
        'interview',
        'communication',
        'notification',
        'document',
        'evidence',
        'internal_referral',
        'external_referral',
        'external_report',
        'appeal',
        'follow_up',
        'closure',
        'special_rule',
        'other',
    ];

    public const DEADLINE_UNITS = ['hours', 'calendar_days', 'business_days', 'school_days', 'external', 'external_defined'];

    protected $table = 'convivencia_protocol_parts';

    protected $fillable = [
        'category',
        'code',
        'title',
        'description',
        'instructions',
        'responsible_label',
        'population_scope',
        'legal_reference',
        'deadline_value',
        'deadline_unit',
        'deadline_anchor',
        'requires_evidence',
        'active',
        'is_sensitive',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'deadline_value' => 'integer',
        'requires_evidence' => 'boolean',
        'active' => 'boolean',
        'is_sensitive' => 'boolean',
        'metadata' => 'array',
    ];

    public function links(): HasMany
    {
        return $this->hasMany(ConvivenciaProtocolPartLink::class, 'protocol_part_id');
    }

    public function activationParts(): HasMany
    {
        return $this->hasMany(ConvivenciaProtocolActivationPart::class, 'protocol_part_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
