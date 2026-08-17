<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParvulariaEvaluation extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_parvularia_evaluations';

    protected function casts(): array
    {
        return ['observed_at' => 'datetime', 'evidence' => 'array'];
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

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ParvulariaPlan::class, 'parvularia_plan_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'evaluator_staff_id');
    }
}
