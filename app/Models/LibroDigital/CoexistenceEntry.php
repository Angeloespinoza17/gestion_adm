<?php

namespace App\Models\LibroDigital;

use App\Models\AcademicYear;
use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaDailyLog;
use App\Models\CourseSection;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoexistenceEntry extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_coexistence_entries';

    protected function casts(): array
    {
        return ['happened_at' => 'datetime', 'guardian_informed' => 'boolean', 'evidence' => 'array'];
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

    public function convivenciaCase(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaCase::class);
    }

    public function convivenciaDailyLog(): BelongsTo
    {
        return $this->belongsTo(ConvivenciaDailyLog::class);
    }

    public function responsibleStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_staff_id');
    }
}
