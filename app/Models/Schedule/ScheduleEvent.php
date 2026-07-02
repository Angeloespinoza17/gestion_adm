<?php

namespace App\Models\Schedule;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleEvent extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CONFLICT = 'conflict';
    public const STATUS_BLOCKED = 'blocked';

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_IMPORTED = 'imported';
    public const SOURCE_GENERATED = 'generated';

    protected $fillable = [
        'academic_year_id',
        'staff_id',
        'teacher_schedule_layer_id',
        'course_section_id',
        'education_level_id',
        'schedule_subject_id',
        'school_day_template_id',
        'school_day_block_id',
        'day_of_week',
        'start_time',
        'end_time',
        'activity_type',
        'pedagogical_hours',
        'minutes',
        'room_id',
        'room_name',
        'status',
        'source',
        'notes',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'pedagogical_hours' => 'decimal:2',
        'minutes' => 'integer',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function layer(): BelongsTo
    {
        return $this->belongsTo(TeacherScheduleLayer::class, 'teacher_schedule_layer_id');
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(ScheduleSubject::class, 'schedule_subject_id');
    }

    public function schoolDayTemplate(): BelongsTo
    {
        return $this->belongsTo(SchoolDayTemplate::class);
    }

    public function schoolDayBlock(): BelongsTo
    {
        return $this->belongsTo(SchoolDayBlock::class);
    }

    public function validationIssues(): HasMany
    {
        return $this->hasMany(ScheduleValidationIssue::class);
    }
}
