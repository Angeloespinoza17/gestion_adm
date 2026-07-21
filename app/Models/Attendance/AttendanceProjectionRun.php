<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceProjectionRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year_id', 'course_section_id', 'method', 'model_version', 'inputs', 'results', 'confidence', 'created_by',
    ];

    protected $casts = ['inputs' => 'array', 'results' => 'array', 'confidence' => 'decimal:2'];
}
