<?php

namespace App\Models\Infirmary;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\MaintenanceDependency;
use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class InfirmaryAccident extends Model
{
    use HasFactory;

    public const SEVERITY_OPTIONS = [
        ['value' => 'leve', 'label' => 'Leve'],
        ['value' => 'moderado', 'label' => 'Moderado'],
        ['value' => 'grave', 'label' => 'Grave'],
        ['value' => 'critico', 'label' => 'Crítico'],
    ];

    public const STATUS_OPTIONS = [
        ['value' => 'abierto', 'label' => 'Abierto'],
        ['value' => 'en_seguimiento', 'label' => 'En seguimiento'],
        ['value' => 'cerrado', 'label' => 'Cerrado'],
    ];

    protected $table = 'infirmary_accidents';

    protected $fillable = [
        'attention_id',
        'student_profile_id',
        'academic_year_id',
        'course_section_id',
        'dependency_id',
        'occurred_at',
        'accident_type',
        'place',
        'activity',
        'description',
        'witnesses',
        'present_staff_id',
        'severity',
        'observed_injuries',
        'first_aid',
        'guardian_call_status',
        'referral_destination',
        'school_insurance',
        'diat_number',
        'diat_generated_at',
        'observations',
        'case_status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'occurred_at' => 'datetime:Y-m-d H:i:s',
        'diat_generated_at' => 'datetime:Y-m-d H:i:s',
        'school_insurance' => 'boolean',
    ];

    public function attention(): BelongsTo
    {
        return $this->belongsTo(InfirmaryAttention::class, 'attention_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function courseSection(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }

    public function dependency(): BelongsTo
    {
        return $this->belongsTo(MaintenanceDependency::class, 'dependency_id');
    }

    public function presentStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'present_staff_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(InfirmaryDocument::class, 'documentable')->latest('id');
    }
}
