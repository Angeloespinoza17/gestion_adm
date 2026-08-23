<?php

namespace App\Models\Grades;

use App\Models\CourseSection;
use App\Models\LibroDigital\Assessment;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\TeacherAssignment;
use App\Models\LibroDigital\TeachingGroup;
use App\Models\Schedule\ScheduleSubject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnnualGradeImportColumn extends Model
{
    protected $guarded = ['id'];

    public function annualImport(): BelongsTo
    {
        return $this->belongsTo(AnnualGradeImport::class, 'annual_grade_import_id');
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(ScheduleSubject::class, 'schedule_subject_id');
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function teachingGroup(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class);
    }

    public function teacherAssignment(): BelongsTo
    {
        return $this->belongsTo(TeacherAssignment::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function cells(): HasMany
    {
        return $this->hasMany(AnnualGradeImportCell::class);
    }
}
