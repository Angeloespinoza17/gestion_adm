<?php

namespace App\Models\Pme;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmeStudentSepClassification extends Model
{
    use SoftDeletes;

    protected $table = 'pme_estudiantes_sep';

    protected $fillable = [
        'student_profile_id',
        'course_section_id',
        'academic_year_id',
        'classification',
        'loaded_at',
        'source',
        'supporting_document_path',
        'supporting_document_name',
        'state',
        'observations',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'loaded_at' => 'date:Y-m-d',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
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
