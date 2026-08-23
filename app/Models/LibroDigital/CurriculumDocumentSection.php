<?php

namespace App\Models\LibroDigital;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumDocumentSection extends LibroDigitalModel
{
    protected $table = 'lcd_curriculum_document_sections';

    protected function casts(): array
    {
        return [
            'official_order' => 'integer',
            'page_start' => 'integer',
            'page_end' => 'integer',
            'structured_data' => 'array',
            'confidence' => 'decimal:4',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(CurriculumDocument::class, 'curriculum_document_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_section_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_section_id')->orderBy('official_order');
    }
}
