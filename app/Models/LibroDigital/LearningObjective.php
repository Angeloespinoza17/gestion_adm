<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\Schedule\ScheduleSubject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LearningObjective extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_learning_objectives';

    protected function casts(): array
    {
        return ['indicators' => 'array', 'active' => 'boolean'];
    }

    public function curriculumCatalog(): BelongsTo
    {
        return $this->belongsTo(CurriculumCatalog::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(ScheduleSubject::class, 'schedule_subject_id');
    }

    public function objectiveSources(): HasMany
    {
        return $this->hasMany(LearningObjectiveSource::class);
    }

    public function curriculumPrograms(): BelongsToMany
    {
        return $this->belongsToMany(CurriculumProgram::class, 'lcd_curriculum_program_objectives');
    }

    public function curriculumUnits(): BelongsToMany
    {
        return $this->belongsToMany(CurriculumUnit::class, 'lcd_curriculum_unit_objectives');
    }
}
