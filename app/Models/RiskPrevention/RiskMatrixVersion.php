<?php

namespace App\Models\RiskPrevention;

use App\Enums\RiskPrevention\RiskMatrixStatus;
use App\Models\Department;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RiskMatrixVersion extends Model
{
    use HasFactory;

    protected $table = 'prevent_risk_matrix_versions';

    protected $guarded = [];

    protected $casts = [
        'status' => RiskMatrixStatus::class,
        'prepared_on' => 'date', 'updated_on' => 'date', 'effective_from' => 'date', 'next_review_at' => 'date',
        'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime', 'archived_at' => 'datetime',
        'snapshot_payload' => 'array', 'lock_version' => 'integer', 'total_workers' => 'integer',
    ];

    public function matrix(): BelongsTo
    {
        return $this->belongsTo(RiskMatrix::class, 'risk_matrix_id');
    }

    public function methodology(): BelongsTo
    {
        return $this->belongsTo(RiskMethodology::class, 'methodology_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function processes(): HasMany
    {
        return $this->hasMany(RiskMatrixProcess::class, 'risk_matrix_version_id')->orderBy('display_order');
    }

    public function participations(): HasMany
    {
        return $this->hasMany(RiskMatrixParticipation::class, 'risk_matrix_version_id')->orderByDesc('participation_date');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(RiskMatrixReview::class, 'risk_matrix_version_id')->orderByDesc('review_date');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(RiskEvidence::class, 'risk_matrix_version_id')->orderByDesc('evidence_date');
    }

    public function program(): HasOne
    {
        return $this->hasOne(PreventiveProgram::class, 'risk_matrix_version_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function programResponsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'program_responsible_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function legalRepresentative(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'legal_representative_id');
    }

    public function sourceVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_version_id');
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }
}
