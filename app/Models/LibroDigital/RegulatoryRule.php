<?php

namespace App\Models\LibroDigital;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegulatoryRule extends LibroDigitalModel
{
    protected $table = 'lcd_regulatory_rules';

    protected function casts(): array
    {
        return [
            'rule_definition' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'active' => 'boolean',
        ];
    }

    public function regulatoryProfile(): BelongsTo
    {
        return $this->belongsTo(RegulatoryProfile::class);
    }
}
