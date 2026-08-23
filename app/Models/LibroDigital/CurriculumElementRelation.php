<?php

namespace App\Models\LibroDigital;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CurriculumElementRelation extends LibroDigitalModel
{
    protected $table = 'lcd_curriculum_element_relations';

    protected function casts(): array
    {
        return ['weight' => 'decimal:4', 'source_page' => 'integer', 'official_order' => 'integer'];
    }

    public function element(): BelongsTo
    {
        return $this->belongsTo(CurriculumElement::class, 'curriculum_element_id');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
