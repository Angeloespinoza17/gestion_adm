<?php

namespace App\Models\LibroDigital;

use App\Enums\LibroDigital\AttendanceStatus;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionAttendance extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_session_attendance';

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'arrival_at' => 'datetime',
            'departure_at' => 'datetime',
            'recorded_at' => 'datetime',
        ];
    }

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function rosterSnapshotItem(): BelongsTo
    {
        return $this->belongsTo(RosterSnapshotItem::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function justification(): BelongsTo
    {
        return $this->belongsTo(AttendanceJustification::class, 'attendance_justification_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
