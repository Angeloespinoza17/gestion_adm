<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingScheme extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_grading_schemes';

    protected function casts(): array
    {
        return [
            'minimum_value' => 'decimal:4',
            'maximum_value' => 'decimal:4',
            'passing_value' => 'decimal:4',
            'equivalences' => 'array',
            'rules' => 'array',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'active' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function regulatoryProfile(): BelongsTo
    {
        return $this->belongsTo(RegulatoryProfile::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }
}
