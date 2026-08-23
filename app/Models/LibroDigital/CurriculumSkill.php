<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumSkill extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_curriculum_skills';

    public function formulations(): HasMany
    {
        return $this->hasMany(CurriculumSkillFormulation::class);
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(CurriculumUnit::class, 'lcd_curriculum_unit_skills');
    }
}
