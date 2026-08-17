<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumCatalog extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_curriculum_catalogs';

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'active' => 'boolean',
        ];
    }

    public function normativeSource(): BelongsTo
    {
        return $this->belongsTo(NormativeSource::class);
    }

    public function learningObjectives(): HasMany
    {
        return $this->hasMany(LearningObjective::class);
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(CurriculumImportBatch::class);
    }

    public function importEvidences(): HasMany
    {
        return $this->hasMany(CurriculumImportEvidence::class);
    }

    public function activations(): HasMany
    {
        return $this->hasMany(CurriculumCatalogActivation::class);
    }

    public function curriculumSources(): HasMany
    {
        return $this->hasMany(CurriculumSource::class);
    }
}
