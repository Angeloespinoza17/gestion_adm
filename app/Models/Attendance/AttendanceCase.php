<?php

namespace App\Models\Attendance;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceCase extends Model
{
    public const STATUSES = ['detected', 'observation', 'family_contact', 'cause_assessment', 'active_plan', 'follow_up', 'improvement', 'closed', 'reopened'];

    protected $fillable = [
        'folio', 'student_profile_id', 'academic_year_id', 'course_section_id', 'opened_from_alert_id',
        'responsible_user_id', 'reference_adult_user_id', 'reference_adult_name', 'status', 'priority',
        'initial_situation', 'opened_at', 'first_intervention_at', 'last_intervention_at',
        'next_review_on', 'closed_at', 'closure_reason', 'closure_notes', 'closed_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'opened_at' => 'datetime', 'first_intervention_at' => 'datetime', 'last_intervention_at' => 'datetime',
        'next_review_on' => 'date:Y-m-d', 'closed_at' => 'datetime',
    ];

    public function studentProfile(): BelongsTo { return $this->belongsTo(StudentProfile::class); }
    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class); }
    public function courseSection(): BelongsTo { return $this->belongsTo(CourseSection::class); }
    public function responsible(): BelongsTo { return $this->belongsTo(User::class, 'responsible_user_id'); }
    public function referenceAdult(): BelongsTo { return $this->belongsTo(User::class, 'reference_adult_user_id'); }
    public function openedFromAlert(): BelongsTo { return $this->belongsTo(AttendanceAlert::class, 'opened_from_alert_id'); }
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'attendance_case_participants')
            ->withPivot(['participation_role', 'active', 'joined_at', 'ended_at', 'added_by'])
            ->withTimestamps();
    }
    public function causes(): HasMany { return $this->hasMany(AttendanceCaseCause::class); }
    public function statusHistory(): HasMany { return $this->hasMany(AttendanceCaseStatusHistory::class)->latest('changed_at'); }
    public function notes(): HasMany { return $this->hasMany(AttendanceCaseNote::class)->latest(); }
    public function familyContacts(): HasMany { return $this->hasMany(AttendanceFamilyContact::class)->latest('contacted_at'); }
    public function interventions(): HasMany { return $this->hasMany(AttendanceIntervention::class); }
    public function plans(): HasMany { return $this->hasMany(AttendanceActionPlan::class)->latest('starts_on'); }
    public function agreements(): HasMany { return $this->hasMany(AttendanceMeetingAgreement::class)->latest('meeting_date'); }
}
