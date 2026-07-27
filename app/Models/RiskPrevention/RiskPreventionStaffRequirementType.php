<?php

namespace App\Models\RiskPrevention;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiskPreventionStaffRequirementType extends Model
{
    public const KIND_TRAINING = 'training';

    public const KIND_DOCUMENT = 'document';

    protected $table = 'prevent_staff_requirement_types';

    protected $fillable = [
        'name',
        'code',
        'kind',
        'validity_months',
        'requires_evidence',
        'is_mandatory',
        'active',
        'sort_order',
        'description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'validity_months' => 'integer',
        'requires_evidence' => 'boolean',
        'is_mandatory' => 'boolean',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function compliances(): HasMany
    {
        return $this->hasMany(RiskPreventionStaffCompliance::class, 'requirement_type_id');
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(RiskPreventionTraining::class, 'requirement_type_id');
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
