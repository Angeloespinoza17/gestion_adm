<?php

namespace App\Models\LibroDigital;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\Schedule\ScheduleSubject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeachingGroup extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_teaching_groups';

    protected function casts(): array
    {
        return ['valid_from' => 'date', 'valid_to' => 'date', 'metadata' => 'array'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(ScheduleSubject::class, 'schedule_subject_id');
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    public function enrollmentLinks(): HasMany
    {
        return $this->hasMany(EnrollmentLink::class);
    }

    public function rosterSnapshots(): HasMany
    {
        return $this->hasMany(RosterSnapshot::class);
    }
}
