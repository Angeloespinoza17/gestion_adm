<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEnrollmentMovement extends Model
{
    use HasFactory;

    public const TYPE_OPTIONS = [
        ['value' => 'matricula', 'label' => 'Matrícula'],
        ['value' => 'cambio_curso', 'label' => 'Cambio de curso'],
        ['value' => 'retiro', 'label' => 'Retiro'],
        ['value' => 'reingreso', 'label' => 'Reingreso'],
    ];

    protected $fillable = [
        'student_enrollment_id',
        'student_profile_id',
        'academic_year_id',
        'from_course_section_id',
        'to_course_section_id',
        'movement_type',
        'effective_date',
        'from_status',
        'to_status',
        'notes',
        'snapshot_year_name',
        'snapshot_from_course_display_name',
        'snapshot_to_course_display_name',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'effective_date' => 'date:Y-m-d',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function fromCourseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'from_course_section_id');
    }

    public function toCourseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'to_course_section_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function snapshotPayload(AcademicYear $academicYear, ?CourseSection $fromCourseSection = null, ?CourseSection $toCourseSection = null): array
    {
        return [
            'snapshot_year_name' => $academicYear->name,
            'snapshot_from_course_display_name' => $fromCourseSection?->display_name,
            'snapshot_to_course_display_name' => $toCourseSection?->display_name,
        ];
    }
}
