<?php

namespace App\Models\Attendance;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRiskSnapshot extends Model
{
    protected $fillable = [
        'student_profile_id', 'academic_year_id', 'course_section_id', 'snapshot_date',
        'school_days_elapsed', 'days_present', 'days_absent', 'justified_absences',
        'unjustified_absences', 'late_arrivals', 'early_departures', 'attendance_percentage',
        'attendance_last_30_days', 'attendance_last_15_days', 'risk_level', 'risk_score',
        'trend', 'trend_points', 'consecutive_absences', 'risk_reasons', 'metrics',
    ];

    protected $casts = [
        'snapshot_date' => 'date:Y-m-d',
        'attendance_percentage' => 'float',
        'attendance_last_30_days' => 'float',
        'attendance_last_15_days' => 'float',
        'trend_points' => 'float',
        'risk_score' => 'integer',
        'risk_reasons' => 'array',
        'metrics' => 'array',
    ];

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function patterns(): HasMany
    {
        return $this->hasMany(AttendancePatternDetection::class, 'student_profile_id', 'student_profile_id');
    }
}
