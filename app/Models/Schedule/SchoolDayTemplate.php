<?php

namespace App\Models\Schedule;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolDayTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year_id',
        'name',
        'start_time',
        'end_time',
        'days_of_week',
        'active',
        'notes',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'active' => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(SchoolDayBlock::class)->orderBy('day_of_week')->orderBy('order')->orderBy('start_time');
    }

    public function educationLevels(): HasMany
    {
        return $this->hasMany(EducationLevel::class, 'default_school_day_template_id');
    }

    public function courseSections(): HasMany
    {
        return $this->hasMany(CourseSection::class, 'school_day_template_id');
    }
}
