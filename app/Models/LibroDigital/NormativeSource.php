<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NormativeSource extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_normative_sources';

    protected function casts(): array
    {
        return ['published_on' => 'date', 'consulted_at' => 'datetime', 'metadata' => 'array'];
    }

    public function regulatoryProfile(): BelongsTo
    {
        return $this->belongsTo(RegulatoryProfile::class);
    }
}
