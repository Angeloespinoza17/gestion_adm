<?php

namespace App\Models\Attendance;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    public const PRESENT = 'present';

    public const ABSENT = 'absent';

    protected $fillable = [
        'attendance_import_id', 'school_day_id', 'academic_year_id', 'course_section_id',
        'student_profile_id', 'student_enrollment_id', 'attendance_date', 'status',
        'absence_reason_id', 'is_justified', 'minutes_late', 'early_departure',
        'arrival_time', 'departure_time', 'corrected_at', 'correction_reason',
        'origin', 'source_symbol', 'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'attendance_date' => 'date:Y-m-d',
        'is_justified' => 'boolean',
        'minutes_late' => 'integer',
        'early_departure' => 'boolean',
        'corrected_at' => 'datetime',
    ];

    public function attendanceImport(): BelongsTo
    {
        return $this->belongsTo(AttendanceImport::class);
    }

    public function schoolDay(): BelongsTo
    {
        return $this->belongsTo(SchoolDay::class);
    }

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

    public function studentEnrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class);
    }

    public function absenceReason(): BelongsTo
    {
        return $this->belongsTo(AttendanceAbsenceReason::class, 'absence_reason_id');
    }
}
