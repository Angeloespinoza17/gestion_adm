<?php

namespace App\Models\Remuneration;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemunerationPayslipConceptRule extends RemunerationModel
{
    protected $casts = [
        'is_imponible' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function concept(): BelongsTo
    {
        return $this->belongsTo(RemunerationConcept::class, 'concept_id');
    }
}
