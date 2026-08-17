<?php

namespace App\Models\LibroDigital;

use App\Enums\LibroDigital\AbsenceCaseStatus;
use App\Models\AcademicYear;
use App\Models\Attendance\AttendanceIntervention;
use App\Models\CourseSection;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbsenceCase extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_absence_cases';

    protected function casts(): array
    {
        return [
            'status' => AbsenceCaseStatus::class,
            'detected_on' => 'date',
            'absence_started_on' => 'date',
            'last_absence_on' => 'date',
            'next_deadline_on' => 'date',
            'initial_snapshot' => 'array',
            'closed_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function attendanceIntervention(): BelongsTo
    {
        return $this->belongsTo(AttendanceIntervention::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AbsenceCaseAction::class);
    }
}
