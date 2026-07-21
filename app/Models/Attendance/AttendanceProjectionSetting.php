<?php

namespace App\Models\Attendance;

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceProjectionSetting extends Model
{
    protected $fillable = [
        'academic_year_id', 'monthly_unit_value', 'attendance_factor',
        'target_attendance_rate', 'conservative_delta', 'custom_attendance_rate',
        'additional_adjustments', 'annual_school_days', 'calculation_window',
        'valid_from', 'valid_to', 'configuration_source', 'currency', 'updated_by',
    ];

    protected $casts = [
        'monthly_unit_value' => 'decimal:4',
        'attendance_factor' => 'decimal:6',
        'target_attendance_rate' => 'decimal:2',
        'conservative_delta' => 'decimal:2',
        'custom_attendance_rate' => 'decimal:2',
        'additional_adjustments' => 'decimal:4',
        'annual_school_days' => 'integer',
        'valid_from' => 'date:Y-m-d',
        'valid_to' => 'date:Y-m-d',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
