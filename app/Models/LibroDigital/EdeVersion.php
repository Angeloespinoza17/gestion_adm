<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EdeVersion extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_ede_versions';

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'metadata' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(EdeMapping::class);
    }

    public function exports(): HasMany
    {
        return $this->hasMany(EdeExport::class);
    }
}
