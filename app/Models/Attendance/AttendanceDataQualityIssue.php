<?php

namespace App\Models\Attendance;

use App\Models\CourseSection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceDataQualityIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year_id', 'course_section_id', 'student_profile_id', 'fingerprint', 'type', 'severity',
        'status', 'title', 'description', 'suggested_action', 'context', 'detected_at', 'resolved_at',
        'assigned_to', 'resolved_by',
    ];

    protected $casts = ['context' => 'array', 'detected_at' => 'datetime', 'resolved_at' => 'datetime'];

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }
}
