<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegulatoryProfile extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_regulatory_profiles';

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'rules_snapshot' => 'array',
            'active' => 'boolean',
        ];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(RegulatoryRule::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(NormativeSource::class);
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}
