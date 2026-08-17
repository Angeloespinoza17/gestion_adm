<?php

namespace App\Models\LibroDigital;

use App\Models\AcademicYear;
use App\Models\Schedule\ScheduleSubject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectCurriculumLink extends LibroDigitalModel
{
    protected $table = 'lcd_subject_curriculum_links';

    protected function casts(): array
    {
        return ['valid_from' => 'date', 'valid_to' => 'date', 'active' => 'boolean'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(ScheduleSubject::class, 'schedule_subject_id');
    }

    public function curriculumCatalog(): BelongsTo
    {
        return $this->belongsTo(CurriculumCatalog::class);
    }
}
