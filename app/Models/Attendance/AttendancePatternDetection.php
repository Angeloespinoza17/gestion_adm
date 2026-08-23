<?php

namespace App\Models\Attendance;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePatternDetection extends Model
{
    protected $fillable = [
        'student_profile_id', 'academic_year_id', 'course_section_id', 'pattern_type',
        'severity', 'confidence_score', 'confidence_label', 'occurrence_count', 'description',
        'metrics', 'period', 'first_detected_at', 'last_detected_at', 'is_active', 'resolved_at',
    ];

    protected $casts = [
        'confidence_score' => 'float',
        'metrics' => 'array',
        'period' => 'array',
        'first_detected_at' => 'datetime',
        'last_detected_at' => 'datetime',
        'is_active' => 'boolean',
        'resolved_at' => 'datetime',
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
}
