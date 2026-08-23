<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CurriculumUnit extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_curriculum_units';

    protected function casts(): array
    {
        return [
            'semester' => 'integer',
            'official_order' => 'integer',
            'estimated_pedagogical_hours' => 'integer',
            'page_start' => 'integer',
            'page_end' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(CurriculumProgram::class, 'curriculum_program_id');
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(CurriculumDocument::class, 'source_document_id');
    }

    public function learningObjectives(): BelongsToMany
    {
        return $this->belongsToMany(LearningObjective::class, 'lcd_curriculum_unit_objectives')
            ->withPivot(['role', 'official_order', 'source_document_id', 'source_page', 'original_text'])
            ->withTimestamps();
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(CurriculumSkill::class, 'lcd_curriculum_unit_skills')
            ->withPivot(['official_order', 'source_document_id', 'source_page', 'original_text'])
            ->withTimestamps();
    }

    public function attitudes(): BelongsToMany
    {
        return $this->belongsToMany(CurriculumAttitude::class, 'lcd_curriculum_unit_attitudes')
            ->withPivot(['official_order', 'source_document_id', 'source_page', 'original_text'])
            ->withTimestamps();
    }

    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(CurriculumKeyword::class, 'lcd_curriculum_unit_keywords')
            ->withPivot(['official_order', 'source_document_id', 'source_page', 'original_text'])
            ->withTimestamps();
    }
}
