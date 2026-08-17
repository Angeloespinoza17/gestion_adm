<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EdeMapping extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_ede_mappings';

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'transform_definition' => 'array',
            'validation_definition' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'active' => 'boolean',
        ];
    }

    public function edeVersion(): BelongsTo
    {
        return $this->belongsTo(EdeVersion::class);
    }

    public function regulatoryProfile(): BelongsTo
    {
        return $this->belongsTo(RegulatoryProfile::class);
    }
}
