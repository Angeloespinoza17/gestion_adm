<?php

namespace App\Models\Attendance;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceFinancialParameter extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'academic_year_id', 'name', 'subsidy_type', 'unit_value', 'attendance_factor', 'currency',
        'valid_from', 'valid_to', 'source_reference', 'assumptions', 'active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'unit_value' => 'decimal:4', 'attendance_factor' => 'decimal:6', 'valid_from' => 'date:Y-m-d',
        'valid_to' => 'date:Y-m-d', 'active' => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
