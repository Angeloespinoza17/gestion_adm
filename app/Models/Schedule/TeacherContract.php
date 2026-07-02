<?php

namespace App\Models\Schedule;

use App\Models\AcademicYear;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherContract extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'academic_year_id',
        'weekly_contract_hours',
        'hour_type',
        'lective_percentage',
        'non_lective_percentage',
        'calculated_lective_hours',
        'calculated_non_lective_hours',
        'valid_from',
        'valid_to',
        'active',
    ];

    protected $casts = [
        'weekly_contract_hours' => 'decimal:2',
        'lective_percentage' => 'decimal:2',
        'non_lective_percentage' => 'decimal:2',
        'calculated_lective_hours' => 'decimal:2',
        'calculated_non_lective_hours' => 'decimal:2',
        'valid_from' => 'date:Y-m-d',
        'valid_to' => 'date:Y-m-d',
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
}
