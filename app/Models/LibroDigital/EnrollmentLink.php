<?php

namespace App\Models\LibroDigital;

use App\Models\CourseSection;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentLink extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_enrollment_links';

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date', 'metadata' => 'array'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function teachingGroup(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class);
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
}
