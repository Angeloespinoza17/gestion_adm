<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPromotion extends Model
{
    use HasFactory;

    public const STATUS_OPTIONS = [
        ['value' => 'promovida', 'label' => 'Promovida'],
        ['value' => 'repitente', 'label' => 'Repitente'],
        ['value' => 'cambio_paralelo', 'label' => 'Cambio de paralelo'],
        ['value' => 'retirada', 'label' => 'Retirada'],
        ['value' => 'egresada', 'label' => 'Egresada'],
    ];

    protected $fillable = [
        'student_profile_id',
        'from_academic_year_id',
        'to_academic_year_id',
        'from_course_section_id',
        'to_course_section_id',
        'promotion_status',
        'notes',
        'created_by',
        'updated_by',
    ];

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function fromAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'from_academic_year_id');
    }

    public function toAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'to_academic_year_id');
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
}
