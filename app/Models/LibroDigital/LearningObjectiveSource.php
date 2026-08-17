<?php

namespace App\Models\LibroDigital;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningObjectiveSource extends LibroDigitalModel
{
    protected $table = 'lcd_learning_objective_sources';

    protected function casts(): array
    {
        return ['source_snapshot' => 'array'];
    }

    public function learningObjective(): BelongsTo
    {
        return $this->belongsTo(LearningObjective::class);
    }

    public function curriculumSource(): BelongsTo
    {
        return $this->belongsTo(CurriculumSource::class);
    }
}
