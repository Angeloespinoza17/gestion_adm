<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StudentProfile extends Model
{
    use HasFactory;

    public const GENERAL_STATUS_OPTIONS = [
        ['value' => 'activo', 'label' => 'Activo'],
        ['value' => 'retirado', 'label' => 'Retirado'],
        ['value' => 'egresado', 'label' => 'Egresado'],
        ['value' => 'suspendido', 'label' => 'Suspendido'],
    ];

    protected $fillable = [
        'first_name',
        'last_name',
        'registered_name',
        'rut',
        'birthdate',
        'gender',
        'nationality',
        'email',
        'phone',
        'address',
        'commune',
        'school_admission_date',
        'previous_school',
        'emergency_contact_name',
        'emergency_contact_phone',
        'religion',
        'accepts_religion_classes',
        'ethnicity',
        'general_status',
        'observations',
        'pickup_restriction',
        'pickup_restriction_notes',
        'porter_alert_notes',
        'authorized_pickup_people',
        'tardiness_semester_one_notes',
        'absence_notes',
        'guardian_name',
        'guardian_relationship',
        'guardian_role',
        'guardian_rut',
        'guardian_passport',
        'guardian_phone',
        'guardian_address',
        'guardian_commune',
        'guardian_photo_authorization',
        'guardian_pickup_authorization',
        'guardian_marital_status',
        'guardian_education_level',
        'guardian_last_education_level',
        'guardian_occupation',
        'guardian_email',
        'guardian_backup_name',
        'guardian_backup_relationship',
        'guardian_backup_role',
        'guardian_backup_rut',
        'guardian_backup_passport',
        'guardian_backup_address',
        'guardian_backup_commune',
        'guardian_backup_photo_authorization',
        'guardian_backup_pickup_authorization',
        'guardian_backup_marital_status',
        'guardian_backup_education_level',
        'guardian_backup_last_education_level',
        'guardian_backup_occupation',
        'guardian_backup_phone',
        'guardian_backup_email',
        'lives_with',
        'siblings_in_school',
        'father_name',
        'father_rut',
        'father_nationality',
        'father_address',
        'father_email',
        'father_occupation',
        'father_phone',
        'father_birthdate',
        'father_education_level',
        'mother_name',
        'mother_rut',
        'mother_nationality',
        'mother_address',
        'mother_email',
        'mother_occupation',
        'mother_phone',
        'mother_birthdate',
        'mother_education_level',
        'has_repeated_course',
        'has_internet',
        'has_computer',
        'health_insurance',
        'height_cm',
        'weight_kg',
        'blood_type',
        'food_allergies',
        'beneficiary_programs',
        'scholarships',
        'has_judicial_process',
        'has_chronic_illness',
        'chronic_illness_details',
        'has_medication_allergies',
        'medication_allergies_details',
        'contraindicated_medications',
        'fit_for_physical_education',
        'has_private_school_insurance',
        'healthcare_provider',
        'health_observations',
        'is_pie_participant',
        'pie_permanence_type',
        'pie_diagnosis',
        'has_physical_restrictions',
        'physical_restrictions_details',
        'baptism_date',
        'baptism_place',
        'first_communion_date',
        'first_communion_place',
        'confirmation_date',
        'confirmation_place',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'birthdate' => 'date:Y-m-d',
        'school_admission_date' => 'date:Y-m-d',
        'father_birthdate' => 'date:Y-m-d',
        'mother_birthdate' => 'date:Y-m-d',
        'baptism_date' => 'date:Y-m-d',
        'first_communion_date' => 'date:Y-m-d',
        'confirmation_date' => 'date:Y-m-d',
        'pickup_restriction' => 'boolean',
        'accepts_religion_classes' => 'boolean',
        'guardian_photo_authorization' => 'boolean',
        'guardian_pickup_authorization' => 'boolean',
        'guardian_backup_photo_authorization' => 'boolean',
        'guardian_backup_pickup_authorization' => 'boolean',
        'authorized_pickup_people' => 'array',
        'height_cm' => 'decimal:2',
        'weight_kg' => 'decimal:2',
        'has_repeated_course' => 'boolean',
        'has_internet' => 'boolean',
        'has_computer' => 'boolean',
        'has_judicial_process' => 'boolean',
        'has_chronic_illness' => 'boolean',
        'has_medication_allergies' => 'boolean',
        'has_physical_restrictions' => 'boolean',
        'fit_for_physical_education' => 'boolean',
        'has_private_school_insurance' => 'boolean',
        'is_pie_participant' => 'boolean',
    ];

    protected $appends = [
        'full_name',
        'registered_name_resolved',
    ];

    public function getFullNameAttribute(): string
    {
        return trim(sprintf('%s %s', $this->first_name, $this->last_name));
    }

    public function getRegisteredNameResolvedAttribute(): string
    {
        return trim((string) ($this->registered_name ?: $this->full_name));
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'student_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class)
            ->with(['academicYear', 'courseSection.educationLevel'])
            ->orderByDesc('academic_year_id')
            ->orderByDesc('id');
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(StudentPromotion::class)->orderByDesc('id');
    }

    public function enrollmentMovements(): HasMany
    {
        return $this->hasMany(StudentEnrollmentMovement::class)
            ->orderByDesc('effective_date')
            ->orderByDesc('id');
    }

    public function porterWithdrawals(): HasMany
    {
        return $this->hasMany(PorterStudentWithdrawal::class)->orderByDesc('withdrawn_at')->orderByDesc('id');
    }

    public function porterReceivedItems(): HasMany
    {
        return $this->hasMany(PorterReceivedItem::class)->orderByDesc('received_at')->orderByDesc('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function preferredEnrollment(?AcademicYear $activeYear = null): ?StudentEnrollment
    {
        $enrollments = $this->relationLoaded('enrollments')
            ? $this->enrollments
            : $this->enrollments()->get();

        if ($activeYear) {
            $match = $enrollments->firstWhere('academic_year_id', $activeYear->id);
            if ($match) {
                return $match;
            }
        }

        return $enrollments
            ->sort(function (StudentEnrollment $left, StudentEnrollment $right) {
                $leftYear = $left->academicYear?->year ?? 0;
                $rightYear = $right->academicYear?->year ?? 0;

                if ($leftYear === $rightYear) {
                    return $right->id <=> $left->id;
                }

                return $rightYear <=> $leftYear;
            })
            ->first();
    }

    public function latestEnrollment(): ?StudentEnrollment
    {
        $enrollments = $this->relationLoaded('enrollments')
            ? $this->enrollments
            : $this->enrollments()->get();

        return $enrollments
            ->sort(function (StudentEnrollment $left, StudentEnrollment $right) {
                $leftYear = $left->academicYear?->year ?? 0;
                $rightYear = $right->academicYear?->year ?? 0;

                if ($leftYear === $rightYear) {
                    return $right->id <=> $left->id;
                }

                return $rightYear <=> $leftYear;
            })
            ->first();
    }

    public function matchingEnrollment(
        ?int $academicYearId = null,
        ?int $courseSectionId = null,
        ?int $educationLevelId = null,
        ?string $sectionName = null,
    ): ?StudentEnrollment {
        $enrollments = $this->relationLoaded('enrollments')
            ? $this->enrollments
            : $this->enrollments()->get();

        return $enrollments->first(function (StudentEnrollment $enrollment) use (
            $academicYearId,
            $courseSectionId,
            $educationLevelId,
            $sectionName,
        ) {
            if ($academicYearId && (int) $enrollment->academic_year_id !== $academicYearId) {
                return false;
            }

            if ($courseSectionId && (int) $enrollment->course_section_id !== $courseSectionId) {
                return false;
            }

            $courseSection = $enrollment->courseSection;

            if ($educationLevelId && (int) ($courseSection?->education_level_id ?? 0) !== $educationLevelId) {
                return false;
            }

            if ($sectionName !== null && $sectionName !== '' && (string) ($courseSection?->section_name ?? '') !== $sectionName) {
                return false;
            }

            return true;
        });
    }
}
