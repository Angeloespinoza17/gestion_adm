<?php

namespace App\Models\LibroDigital;

use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RosterSnapshotItem extends LibroDigitalModel
{
    protected $table = 'lcd_roster_snapshot_items';

    protected function casts(): array
    {
        return ['active_from' => 'date', 'active_to' => 'date'];
    }

    public function rosterSnapshot(): BelongsTo
    {
        return $this->belongsTo(RosterSnapshot::class);
    }

    public function enrollmentLink(): BelongsTo
    {
        return $this->belongsTo(EnrollmentLink::class);
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
