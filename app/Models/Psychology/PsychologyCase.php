<?php

namespace App\Models\Psychology;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PsychologyCase extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'psychology_cases';

    protected $fillable = ['code', 'student_profile_id', 'origin_referral_id', 'responsible_user_id', 'status', 'priority', 'confidentiality', 'general_reason', 'categories', 'objectives', 'next_action', 'next_review_on', 'opened_at', 'last_activity_at', 'guardian_information_status', 'closed_at', 'closure_reason', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = ['next_review_on' => 'date:Y-m-d', 'opened_at' => 'datetime', 'last_activity_at' => 'datetime', 'closed_at' => 'datetime'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function originReferral(): BelongsTo
    {
        return $this->belongsTo(PsychologyReferral::class, 'origin_referral_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(PsychologyReferral::class, 'case_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PsychologyCaseAssignment::class, 'case_id')->orderByDesc('assigned_at');
    }

    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'psychology_case_participants', 'case_id', 'user_id')->withPivot(['participation_role', 'visibility'])->withTimestamps();
    }

    public function plans(): HasMany
    {
        return $this->hasMany(PsychologyInterventionPlan::class, 'case_id')->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(PsychologyActivity::class, 'case_id')->orderByDesc('activity_on');
    }

    public function riskAssessments(): HasMany
    {
        return $this->hasMany(PsychologyRiskAssessment::class, 'case_id')->latest();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(PsychologyTask::class, 'case_id')->orderBy('due_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PsychologyDocument::class, 'case_id')->latest();
    }

    public function consents(): HasMany
    {
        return $this->hasMany(PsychologyConsent::class, 'case_id')->latest();
    }

    public function externalReferrals(): HasMany
    {
        return $this->hasMany(PsychologyExternalReferral::class, 'case_id')->latest('referred_on');
    }

    public function sharedFeedback(): HasMany
    {
        return $this->hasMany(PsychologySharedFeedback::class, 'case_id')->latest();
    }

    public function closures(): HasMany
    {
        return $this->hasMany(PsychologyCaseClosure::class, 'case_id')->latest('closed_at');
    }

    public function reopenings(): HasMany
    {
        return $this->hasMany(PsychologyCaseReopening::class, 'case_id')->latest('reopened_at');
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($filters['priority'] ?? null, fn (Builder $q, $v) => $q->where('priority', $v))
            ->when($filters['responsible_user_id'] ?? null, fn (Builder $q, $v) => $q->where('responsible_user_id', $v))
            ->when($filters['search'] ?? null, fn (Builder $q, $v) => $q->where(fn (Builder $s) => $s->where('code', 'like', "%{$v}%")->orWhereHas('student', fn (Builder $student) => $student->where('first_name', 'like', "%{$v}%")->orWhere('last_name', 'like', "%{$v}%"))));
    }
}
