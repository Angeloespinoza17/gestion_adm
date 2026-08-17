<?php

namespace App\Models\LibroDigital;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureFlag extends LibroDigitalModel
{
    protected $table = 'lcd_feature_flags';

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'configuration' => 'array'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
