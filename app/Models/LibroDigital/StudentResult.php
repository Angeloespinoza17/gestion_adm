<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentResult extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_student_results';

    protected function casts(): array
    {
        return [
            'raw_score' => 'decimal:4',
            'numeric_value' => 'decimal:4',
            'normalized_percentage' => 'decimal:4',
            'absent' => 'boolean',
            'exempt' => 'boolean',
            'recorded_at' => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function enrollmentLink(): BelongsTo
    {
        return $this->belongsTo(EnrollmentLink::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
