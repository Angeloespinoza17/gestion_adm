<?php

namespace App\Models\LibroDigital;

use App\Models\AcademicYear;
use App\Models\ApoyoProfesional\ApoyoAtencion;
use App\Models\ApoyoProfesional\ApoyoPlan;
use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PieSupportRecord extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_pie_support_records';

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime', 'evidence' => 'array'];
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

    public function sourceAttention(): BelongsTo
    {
        return $this->belongsTo(ApoyoAtencion::class, 'apoyo_atencion_id');
    }

    public function sourcePlan(): BelongsTo
    {
        return $this->belongsTo(ApoyoPlan::class, 'apoyo_plan_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'professional_staff_id');
    }
}
