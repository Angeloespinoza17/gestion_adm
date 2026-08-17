<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\Schedule\ScheduleSubject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumSource extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_curriculum_sources';

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date', 'metadata' => 'array'];
    }

    public function curriculumCatalog(): BelongsTo
    {
        return $this->belongsTo(CurriculumCatalog::class);
    }

    public function normativeSource(): BelongsTo
    {
        return $this->belongsTo(NormativeSource::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(ScheduleSubject::class, 'schedule_subject_id');
    }

    public function objectiveSources(): HasMany
    {
        return $this->hasMany(LearningObjectiveSource::class);
    }
}
