<?php

namespace App\Models\LibroDigital;

use App\Models\EducationLevel;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\Schedule\ScheduleSubject;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumProgram extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_curriculum_programs';

    protected function casts(): array
    {
        return [
            'estimated_weeks' => 'integer',
            'estimated_pedagogical_hours' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'published_at' => 'datetime',
            'revision' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(ScheduleSubject::class, 'schedule_subject_id');
    }

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(CurriculumVersion::class, 'curriculum_version_id');
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(CurriculumCatalog::class, 'curriculum_catalog_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(CurriculumUnit::class)->orderBy('official_order');
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(CurriculumDocument::class, 'lcd_curriculum_document_program')
            ->withPivot(['role', 'official_order', 'page_start', 'page_end'])
            ->withTimestamps();
    }

    public function axes(): BelongsToMany
    {
        return $this->belongsToMany(CurriculumAxis::class, 'lcd_curriculum_program_axes')
            ->withPivot(['official_order', 'source_document_id', 'source_page', 'original_text'])
            ->withTimestamps();
    }

    public function learningObjectives(): BelongsToMany
    {
        return $this->belongsToMany(LearningObjective::class, 'lcd_curriculum_program_objectives')
            ->withPivot(['role', 'official_order', 'source_document_id', 'source_page', 'original_text'])
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
