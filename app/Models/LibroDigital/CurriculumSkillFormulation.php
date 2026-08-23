<?php

namespace App\Models\LibroDigital;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumSkillFormulation extends LibroDigitalModel
{
    protected $table = 'lcd_curriculum_skill_formulations';

    public function skill(): BelongsTo
    {
        return $this->belongsTo(CurriculumSkill::class, 'curriculum_skill_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(CurriculumProgram::class, 'curriculum_program_id');
    }
}
