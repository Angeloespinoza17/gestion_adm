<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CurriculumKeyword extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_curriculum_keywords';

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(CurriculumUnit::class, 'lcd_curriculum_unit_keywords');
    }
}
