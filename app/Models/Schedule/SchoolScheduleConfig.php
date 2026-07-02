<?php

namespace App\Models\Schedule;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolScheduleConfig extends Model
{
    use HasFactory;

    public const CALCULATION_BASES = ['chronological', 'pedagogical'];
    public const ROUNDING_MODES = ['none', 'nearest', 'up', 'down'];

    protected $fillable = [
        'academic_year_id',
        'pedagogical_hour_minutes',
        'default_lective_percentage',
        'default_non_lective_percentage',
        'calculation_base',
        'rounding_mode',
        'strict_validation_enabled',
    ];

    protected $casts = [
        'pedagogical_hour_minutes' => 'integer',
        'default_lective_percentage' => 'decimal:2',
        'default_non_lective_percentage' => 'decimal:2',
        'strict_validation_enabled' => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
