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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class InfirmaryAttention extends Model
{
    use HasFactory;

    public const PRIORITY_OPTIONS = [
        ['value' => 'baja', 'label' => 'Baja'],
        ['value' => 'media', 'label' => 'Media'],
        ['value' => 'alta', 'label' => 'Alta'],
        ['value' => 'emergencia', 'label' => 'Emergencia'],
    ];

    public const STATUS_OPTIONS = [
        ['value' => 'abierta', 'label' => 'Abierta'],
        ['value' => 'en_atencion', 'label' => 'En atención'],
        ['value' => 'finalizada', 'label' => 'Finalizada'],
    ];

    public const COMPANION_OPTIONS = [
        ['value' => 'sin_acompanante', 'label' => 'Sin acompañante'],
        ['value' => 'inspectora', 'label' => 'Inspectora'],
        ['value' => 'profesor', 'label' => 'Profesor'],
        ['value' => 'apoderado', 'label' => 'Apoderado'],
        ['value' => 'otro', 'label' => 'Otro'],
    ];

    protected $table = 'infirmary_attentions';

    protected $fillable = [
        'student_profile_id',
        'academic_year_id',
        'course_section_id',
        'teacher_staff_id',
        'referred_by_staff_id',
        'dependency_id',
        'attended_by_user_id',
        'attention_category',
        'attended_at',
        'student_full_name_snapshot',
        'student_rut_snapshot',
        'course_name_snapshot',
        'teacher_name_snapshot',
        'age_snapshot',
        'accompanied_by_type',
        'accompanied_by_name',
        'consultation_reason',
        'initial_description',
        'observations',
        'attention_duration_minutes',
        'priority',
        'status',
        'finalized_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'attended_at' => 'datetime:Y-m-d H:i:s',
        'finalized_at' => 'datetime:Y-m-d H:i:s',
    ];

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

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'teacher_staff_id');
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'referred_by_staff_id');
    }

    public function dependency(): BelongsTo
    {
        return $this->belongsTo(MaintenanceDependency::class, 'dependency_id');
    }

    public function attendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attended_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(InfirmaryAttentionTreatment::class, 'attention_id')->latest('id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(InfirmaryAttentionReferral::class, 'attention_id')->latest('referred_at')->latest('id');
    }

    public function calls(): HasMany
    {
        return $this->hasMany(InfirmaryAttentionCall::class, 'attention_id')->latest('called_at')->latest('id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(InfirmaryAttentionFollowUp::class, 'attention_id')->latest('followed_at')->latest('id');
    }

    public function administrations(): HasMany
    {
        return $this->hasMany(InfirmaryMedicationAdministration::class, 'attention_id')->latest('administered_at')->latest('id');
    }

    public function accidents(): HasMany
    {
        return $this->hasMany(InfirmaryAccident::class, 'attention_id')->latest('occurred_at')->latest('id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(InfirmaryDocument::class, 'documentable')->latest('id');
    }
}
