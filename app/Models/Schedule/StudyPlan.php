<?php

namespace App\Models\Schedule;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudyPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year_id',
        'education_level_id',
        'course_section_id',
        'name',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class);
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(StudyPlanSubject::class)->with('scheduleSubject')->orderBy('id');
    }
}
