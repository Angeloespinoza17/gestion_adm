<?php

namespace App\Models\Psychology;

use App\Models\CourseSection;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PsychologyReferral extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'psychology_referrals';

    protected $fillable = [
        'student_profile_id', 'course_section_id', 'referred_by_user_id', 'suggested_user_id',
        'assigned_user_id', 'case_id', 'origin_area', 'source_type', 'source_id', 'status', 'suggested_urgency',
        'professional_priority', 'primary_reason', 'secondary_reasons', 'observed_facts',
        'approximate_started_on', 'people_involved', 'measures_taken', 'known_previous_interventions',
        'observed_risk_indicators', 'immediate_response_needed', 'guardian_informed',
        'guardian_contact_status', 'observations', 'information_request', 'information_response',
        'shared_decision_note', 'internal_decision_note', 'purpose_declaration_accepted',
        'referred_at', 'first_reviewed_at', 'completed_at', 'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = [
        'approximate_started_on' => 'date:Y-m-d',
        'immediate_response_needed' => 'boolean', 'guardian_informed' => 'boolean',
        'purpose_declaration_accepted' => 'boolean', 'referred_at' => 'datetime',
        'first_reviewed_at' => 'datetime', 'completed_at' => 'datetime',
        'internal_decision_note' => 'encrypted',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function suggestedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suggested_user_id');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(PsychologyCase::class, 'case_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(PsychologyReferralStatusHistory::class, 'referral_id')->orderBy('changed_at');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PsychologyCaseAssignment::class, 'referral_id')->orderByDesc('assigned_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PsychologyDocument::class, 'referral_id');
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['status'] ?? null, fn (Builder $q, $value) => $q->where('status', $value))
            ->when($filters['priority'] ?? null, fn (Builder $q, $value) => $q->where(fn (Builder $p) => $p->where('professional_priority', $value)->orWhere(fn (Builder $s) => $s->whereNull('professional_priority')->where('suggested_urgency', $value))))
            ->when($filters['assigned_user_id'] ?? null, fn (Builder $q, $value) => $q->where('assigned_user_id', $value))
            ->when($filters['student_profile_id'] ?? null, fn (Builder $q, $value) => $q->where('student_profile_id', $value))
            ->when($filters['search'] ?? null, function (Builder $q, string $search) {
                $q->where(fn (Builder $term) => $term->where('code', 'like', "%{$search}%")
                    ->orWhere('primary_reason', 'like', "%{$search}%")
                    ->orWhereHas('student', fn (Builder $student) => $student->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")));
            });
    }
}
