<?php

namespace App\Models\LibroDigital;

use App\Enums\LibroDigital\AssessmentStatus;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\Schedule\ScheduleSubject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_assessments';

    protected function casts(): array
    {
        return [
            'status' => AssessmentStatus::class,
            'assessment_date' => 'date',
            'results_due_on' => 'date',
            'weight' => 'decimal:4',
            'maximum_score' => 'decimal:4',
            'instrument_metadata' => 'array',
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

    public function period(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id');
    }

    public function gradingScheme(): BelongsTo
    {
        return $this->belongsTo(GradingScheme::class);
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

    public function teacherAssignment(): BelongsTo
    {
        return $this->belongsTo(TeacherAssignment::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(StudentResult::class);
    }
}
