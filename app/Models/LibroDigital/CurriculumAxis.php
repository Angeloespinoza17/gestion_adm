<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\Schedule\ScheduleSubject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CurriculumAxis extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_curriculum_axes';

    public function subject(): BelongsTo
    {
        return $this->belongsTo(ScheduleSubject::class, 'schedule_subject_id');
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(CurriculumProgram::class, 'lcd_curriculum_program_axes');
    }

    public function learningObjectives(): BelongsToMany
    {
        return $this->belongsToMany(LearningObjective::class, 'lcd_curriculum_axis_objectives')
            ->withPivot(['curriculum_program_id', 'source_document_id', 'source_page'])
            ->withTimestamps();
    }
}
