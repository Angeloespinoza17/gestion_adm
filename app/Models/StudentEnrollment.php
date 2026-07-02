<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentEnrollment extends Model
{
    use HasFactory;

    public const STATUS_OPTIONS = [
        ['value' => 'matriculada', 'label' => 'Matriculada'],
        ['value' => 'regular', 'label' => 'Regular'],
        ['value' => 'retirada', 'label' => 'Retirada'],
        ['value' => 'egresada', 'label' => 'Egresada'],
        ['value' => 'suspendida', 'label' => 'Suspendida'],
        ['value' => 'trasladada', 'label' => 'Trasladada'],
    ];

    public const NON_ROSTER_STATUS_VALUES = [
        'retirada',
        'trasladada',
        'egresada',
    ];

    protected $fillable = [
        'student_profile_id',
        'academic_year_id',
        'course_section_id',
        'enrollment_status',
        'enrolled_at',
        'withdrawn_at',
        'observations',
        'snapshot_year_name',
        'snapshot_level_name',
        'snapshot_section_name',
        'snapshot_course_display_name',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'enrolled_at' => 'date:Y-m-d',
        'withdrawn_at' => 'date:Y-m-d',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StudentEnrollmentMovement::class)
            ->orderByDesc('effective_date')
            ->orderByDesc('id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function snapshotPayload(AcademicYear $academicYear, CourseSection $courseSection): array
    {
        $courseSection->loadMissing('educationLevel');

        return [
            'snapshot_year_name' => $academicYear->name,
            'snapshot_level_name' => $courseSection->educationLevel?->name ?? $courseSection->display_name,
            'snapshot_section_name' => $courseSection->section_name,
            'snapshot_course_display_name' => $courseSection->display_name,
        ];
    }
}
