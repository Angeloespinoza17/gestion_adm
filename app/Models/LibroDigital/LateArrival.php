<?php

namespace App\Models\LibroDigital;

use App\Models\AcademicYear;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LateArrival extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_late_arrivals';

    protected function casts(): array
    {
        return ['arrival_at' => 'datetime', 'evidence' => 'array', 'revision' => 'integer'];
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

    public function teachingGroup(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(LateArrivalPeriod::class, 'late_arrival_period_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(SessionAttendance::class, 'session_attendance_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }
}
