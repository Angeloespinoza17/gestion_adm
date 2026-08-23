<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumVersion extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_curriculum_versions';

    protected function casts(): array
    {
        return [
            'publication_year' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function programs(): HasMany
    {
        return $this->hasMany(CurriculumProgram::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CurriculumDocument::class);
    }
}
