<?php

namespace App\Models;

use App\Models\Schedule\SchoolDayTemplate;
use App\Models\Schedule\ScheduleEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year_id',
        'education_level_id',
        'school_day_template_id',
        'section_name',
        'display_name',
        'capacity',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'active' => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class);
    }

    public function schoolDayTemplate(): BelongsTo
    {
        return $this->belongsTo(SchoolDayTemplate::class);
    }

    public function scheduleEvents(): HasMany
    {
        return $this->hasMany(ScheduleEvent::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class)->orderBy('snapshot_course_display_name');
    }

    public function movementsFrom(): HasMany
    {
        return $this->hasMany(StudentEnrollmentMovement::class, 'from_course_section_id')->orderByDesc('effective_date')->orderByDesc('id');
    }

    public function movementsTo(): HasMany
    {
        return $this->hasMany(StudentEnrollmentMovement::class, 'to_course_section_id')->orderByDesc('effective_date')->orderByDesc('id');
    }

    public function porterWithdrawals(): HasMany
    {
        return $this->hasMany(PorterStudentWithdrawal::class)->orderByDesc('withdrawn_at')->orderByDesc('id');
    }

    public function porterReceivedItems(): HasMany
    {
        return $this->hasMany(PorterReceivedItem::class)->orderByDesc('received_at')->orderByDesc('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function makeDisplayName(EducationLevel $level, string $sectionName): string
    {
        return trim(sprintf('%s %s', $level->name, strtoupper(trim($sectionName))));
    }
}
