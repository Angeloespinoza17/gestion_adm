<?php

namespace App\Models\LibroDigital;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumDocumentPage extends LibroDigitalModel
{
    protected $table = 'lcd_curriculum_document_pages';

    protected function casts(): array
    {
        return [
            'physical_page_number' => 'integer',
            'confidence' => 'decimal:4',
            'has_tables' => 'boolean',
            'has_images' => 'boolean',
            'processing_warnings' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(CurriculumDocument::class, 'curriculum_document_id');
    }
}
