<?php

namespace App\Models\Attendance;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolDay extends Model
{
    protected $fillable = [
        'academic_year_id', 'date', 'is_school_day', 'status', 'source', 'label',
        'metadata', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'is_school_day' => 'boolean',
        'metadata' => 'array',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
