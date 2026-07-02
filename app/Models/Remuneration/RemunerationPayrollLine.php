<?php

namespace App\Models\Remuneration;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemunerationPayrollLine extends RemunerationModel
{
    protected $casts = [
        'is_taxable' => 'boolean',
        'is_imponible' => 'boolean',
        'affects_tax_base' => 'boolean',
        'affects_net' => 'boolean',
        'amount' => 'integer',
        'quantity' => 'decimal:4',
        'unit_value' => 'integer',
        'snapshot' => 'array',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(RemunerationPayroll::class, 'payroll_id');
    }

    public function concept(): BelongsTo
    {
        return $this->belongsTo(RemunerationConcept::class, 'concept_id');
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(RemunerationMovement::class, 'source_movement_id');
    }
}
