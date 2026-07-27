<?php

namespace App\Models\RiskPrevention;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskPreventionStaffCompliance extends Model
{
    protected $table = 'prevent_staff_compliances';

    protected $appends = [
        'current_status',
        'has_evidence',
    ];

    protected $fillable = [
        'staff_id',
        'requirement_type_id',
        'training_id',
        'issued_on',
        'expires_on',
        'is_not_applicable',
        'evidence_path',
        'evidence_name',
        'evidence_mime',
        'evidence_size',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'issued_on' => 'date:Y-m-d',
        'expires_on' => 'date:Y-m-d',
        'is_not_applicable' => 'boolean',
        'evidence_size' => 'integer',
    ];

    public function getHasEvidenceAttribute(): bool
    {
        return filled($this->evidence_path) || filled($this->training?->evidence_path);
    }

    public function getCurrentStatusAttribute(): string
    {
        if ($this->is_not_applicable) {
            return 'no_aplica';
        }

        if (($this->requirement?->requires_evidence ?? true) && ! $this->has_evidence) {
            return 'pendiente';
        }

        if (! $this->issued_on && ! $this->expires_on) {
            return 'pendiente';
        }

        if (! $this->expires_on) {
            return 'vigente';
        }

        if ($this->expires_on->isBefore(today())) {
            return 'vencido';
        }

        if ($this->expires_on->lessThanOrEqualTo(today()->addDays(30))) {
            return 'por_vencer';
        }

        return 'vigente';
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(RiskPreventionStaffRequirementType::class, 'requirement_type_id');
    }

    public function training(): BelongsTo
    {
        return $this->belongsTo(RiskPreventionTraining::class, 'training_id');
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
