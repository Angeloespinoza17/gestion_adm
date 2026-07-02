<?php

namespace App\Models\Schedule;

use App\Models\AcademicYear;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeacherScheduleLayer extends Model
{
    use HasFactory;

    public const TYPE_LECTIVE = 'lective';
    public const TYPE_NON_LECTIVE = 'non_lective';
    public const TYPE_AVAILABILITY_BLOCK = 'availability_block';

    protected $fillable = [
        'staff_id',
        'academic_year_id',
        'name',
        'type',
        'color',
        'visible_by_default',
        'priority',
        'active',
    ];

    protected $casts = [
        'visible_by_default' => 'boolean',
        'priority' => 'integer',
        'active' => 'boolean',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ScheduleEvent::class);
    }
}
