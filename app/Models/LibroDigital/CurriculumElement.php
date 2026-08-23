<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumElement extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_curriculum_elements';

    protected function casts(): array
    {
        return ['structured_data' => 'array'];
    }

    public function relations(): HasMany
    {
        return $this->hasMany(CurriculumElementRelation::class);
    }
}
