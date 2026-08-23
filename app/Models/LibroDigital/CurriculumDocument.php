<?php

namespace App\Models\LibroDigital;

use App\Models\EducationLevel;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\Schedule\ScheduleSubject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumDocument extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_curriculum_documents';

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'publication_year' => 'integer',
            'page_count' => 'integer',
            'classification' => 'array',
            'metadata' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(CurriculumVersion::class, 'curriculum_version_id');
    }

    public function primarySubject(): BelongsTo
    {
        return $this->belongsTo(ScheduleSubject::class, 'primary_subject_id');
    }

    public function primaryEducationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class, 'primary_education_level_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(CurriculumDocumentPage::class)->orderBy('physical_page_number');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(CurriculumDocumentSection::class)->orderBy('official_order');
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(CurriculumProgram::class, 'lcd_curriculum_document_program')
            ->withPivot(['role', 'official_order', 'page_start', 'page_end'])
            ->withTimestamps();
    }

    public function importFiles(): HasMany
    {
        return $this->hasMany(CurriculumImportFile::class);
    }
}
