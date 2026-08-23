<?php

namespace App\Models\Grades;

use App\Models\CourseSection;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnnualGradeImportRow extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'match_confidence' => 'float',
            'candidates' => 'array',
            'matched_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function annualImport(): BelongsTo
    {
        return $this->belongsTo(AnnualGradeImport::class, 'annual_grade_import_id');
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function matchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    public function cells(): HasMany
    {
        return $this->hasMany(AnnualGradeImportCell::class);
    }
}
