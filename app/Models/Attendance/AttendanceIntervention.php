<?php

namespace App\Models\Attendance;

use App\Models\AcademicYear;
use App\Models\Convivencia\ConvivenciaCase;
use App\Models\CourseSection;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceIntervention extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'folio', 'academic_year_id', 'course_section_id', 'student_profile_id', 'attendance_alert_id',
        'convivencia_case_id', 'risk_level_id', 'responsible_user_id', 'status', 'probable_cause',
        'description', 'opened_at', 'first_contact_at', 'first_action_at', 'due_on', 'result',
        'closed_at', 'closure_reason', 'closed_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'opened_at' => 'datetime', 'first_contact_at' => 'datetime', 'first_action_at' => 'datetime',
        'due_on' => 'date:Y-m-d', 'closed_at' => 'datetime',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(AttendanceAlert::class, 'attendance_alert_id');
    }

    public function convivenciaCase(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaCase::class);
    }

    public function riskLevel(): BelongsTo
    {
        return $this->belongsTo(AttendanceRiskLevel::class, 'risk_level_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AttendanceInterventionAction::class)->orderBy('scheduled_at');
    }
}
