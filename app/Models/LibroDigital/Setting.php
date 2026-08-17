<?php

namespace App\Models\LibroDigital;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends LibroDigitalModel
{
    protected $table = 'lcd_settings';

    protected function casts(): array
    {
        return ['value' => 'array', 'is_encrypted' => 'boolean'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
