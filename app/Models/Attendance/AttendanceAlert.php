<?php

namespace App\Models\Attendance;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceAlert extends Model
{
    protected $fillable = [
        'academic_year_id', 'course_section_id', 'student_profile_id', 'type', 'severity',
        'status', 'detected_on', 'metric_value', 'threshold_value', 'title', 'description',
        'context', 'acknowledged_at', 'resolved_at', 'assigned_to', 'acknowledged_by', 'resolved_by',
    ];

    protected $casts = [
        'detected_on' => 'date:Y-m-d',
        'metric_value' => 'decimal:2',
        'threshold_value' => 'decimal:2',
        'context' => 'array',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function followups(): HasMany
    {
        return $this->hasMany(AttendanceFollowup::class)->latest('action_date');
    }
}
