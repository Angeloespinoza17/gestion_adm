<?php

namespace App\Models\LibroDigital;

use App\Enums\LibroDigital\SessionStatus;
use App\Models\AcademicYear;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\Schedule\ScheduleEvent;
use App\Models\Schedule\ScheduleSubject;
use App\Models\Schedule\SchoolDayBlock;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ClassSession extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_class_sessions';

    protected function casts(): array
    {
        return [
            'status' => SessionStatus::class,
            'session_date' => 'date',
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'actual_start_at' => 'datetime',
            'actual_end_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function teachingGroup(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function regulatoryProfile(): BelongsTo
    {
        return $this->belongsTo(RegulatoryProfile::class);
    }

    public function rosterSnapshot(): BelongsTo
    {
        return $this->belongsTo(RosterSnapshot::class);
    }

    public function scheduleEvent(): BelongsTo
    {
        return $this->belongsTo(ScheduleEvent::class);
    }

    public function schoolDayBlock(): BelongsTo
    {
        return $this->belongsTo(SchoolDayBlock::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(ScheduleSubject::class, 'schedule_subject_id');
    }

    public function curriculumProgram(): BelongsTo
    {
        return $this->belongsTo(CurriculumProgram::class, 'curriculum_program_id');
    }

    public function curriculumUnit(): BelongsTo
    {
        return $this->belongsTo(CurriculumUnit::class, 'curriculum_unit_id');
    }

    public function curriculumAxis(): BelongsTo
    {
        return $this->belongsTo(CurriculumAxis::class, 'curriculum_axis_id');
    }

    public function scheduledTeacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'scheduled_teacher_id');
    }

    public function actualTeacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'actual_teacher_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(SessionAttendance::class);
    }

    public function signatures(): MorphMany
    {
        return $this->morphMany(TeacherSignature::class, 'signable');
    }
}
