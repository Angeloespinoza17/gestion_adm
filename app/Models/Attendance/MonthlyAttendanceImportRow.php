<?php

namespace App\Models\Attendance;

use App\Models\CourseSection;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyAttendanceImportRow extends Model
{
    protected $fillable = [
        'monthly_attendance_import_id', 'student_profile_id', 'student_enrollment_id',
        'course_section_id', 'source_sheet', 'source_row', 'source_course_name', 'list_number',
        'given_names', 'paternal_surname', 'maternal_surname', 'source_name', 'source_rut',
        'normalized_rut', 'present_days', 'absent_days', 'class_days', 'attendance_rate',
        'is_sep_priority', 'is_sep_preferential', 'is_pie', 'daily_records', 'match_status',
        'match_confidence', 'candidates', 'resolution_note', 'matched_by', 'matched_at', 'applied_at',
    ];

    protected $casts = [
        'source_row' => 'integer',
        'list_number' => 'integer',
        'present_days' => 'integer',
        'absent_days' => 'integer',
        'class_days' => 'integer',
        'attendance_rate' => 'float',
        'is_sep_priority' => 'boolean',
        'is_sep_preferential' => 'boolean',
        'is_pie' => 'boolean',
        'daily_records' => 'array',
        'candidates' => 'array',
        'match_confidence' => 'float',
        'matched_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function monthlyImport(): BelongsTo
    {
        return $this->belongsTo(MonthlyAttendanceImport::class, 'monthly_attendance_import_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function matchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }
}
