<?php

namespace App\Services\Students;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\Infirmary\InfirmaryAccident;
use App\Models\Infirmary\InfirmaryAttention;
use App\Models\Infirmary\InfirmaryAttentionReferral;
use App\Models\Infirmary\InfirmaryMedicationAdministration;
use App\Models\StudentEnrollment;
use App\Models\StudentEnrollmentMovement;
use App\Models\StudentProfile;
use App\Models\StudentPromotion;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class StudentReportService
{
    public const MISSING_DATA_DIMENSIONS = [
        'enrollment_trend',
        'movement_trend',
        'course',
        'enrollment_status',
        'age',
        'commune',
        'support',
        'quality',
        'ethnicity',
        'religion',
        'student_communes',
        'lives_with',
        'guardian_communes',
        'backup_guardian_communes',
        'guardian_photo_authorizations',
        'backup_guardian_photo_authorizations',
        'guardian_relationships',
        'backup_guardian_relationships',
        'guardian_roles',
        'backup_guardian_roles',
        'guardian_education_levels',
        'backup_guardian_education_levels',
        'father_education_levels',
        'mother_education_levels',
        'guardian_occupations',
        'backup_guardian_occupations',
        'father_occupations',
        'mother_occupations',
        'guardian_marital_statuses',
        'backup_guardian_marital_statuses',
        'parent_nationalities',
        'parent_presence',
        'health_conditions',
        'health_insurance',
        'infirmary_attentions',
    ];

    private const CACHE_TTL_SECONDS = 180;

    private const QUALITY_FIELDS = [
        'first_name',
        'last_name',
        'rut',
        'birthdate',
        'address',
        'commune',
        'guardian_name',
        'guardian_phone',
    ];

    public function summary(array $filters, bool $includeInfirmary = false, bool $refresh = false): array
    {
        unset($filters['refresh']);
        $cacheKey = $this->summaryCacheKey($filters, $includeInfirmary);

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL_SECONDS,
            fn () => $this->build($filters, $includeInfirmary, false),
        );
    }

    public function build(array $filters, bool $includeInfirmary = false, bool $includeDetails = true): array
    {
        $academicYear = $this->resolveAcademicYear($filters['academic_year_id'] ?? null);
        [$from, $to] = $this->resolveRange($academicYear, $filters);

        $enrollments = $this->enrollmentQuery($academicYear, $filters)->get();
        $roster = $enrollments
            ->reject(fn (StudentEnrollment $enrollment) => in_array(
                $enrollment->enrollment_status,
                StudentEnrollment::NON_ROSTER_STATUS_VALUES,
                true,
            ))
            ->values();

        $studentIds = $enrollments->pluck('student_profile_id')->unique()->values();
        $movements = $this->movementQuery($academicYear, $filters, $studentIds, $from, $to)->get();
        $promotions = $this->promotionQuery($academicYear, $filters, $studentIds)->get();
        $courseSections = $this->courseSections($academicYear, $filters);
        $qualityScores = $this->qualityScores($roster);
        $profiles = $roster
            ->pluck('studentProfile')
            ->filter()
            ->unique('id')
            ->values();

        $newEnrollments = $enrollments->filter(
            fn (StudentEnrollment $enrollment) => $this->dateIsWithin($enrollment->enrolled_at, $from, $to)
        );
        $withdrawalEvents = $this->withdrawalEvents($enrollments, $movements, $from, $to);
        $withdrawalStudentIds = $withdrawalEvents->pluck('student_profile_id')->unique();
        $inferredWithdrawalStudentIds = $withdrawalEvents
            ->where('date_inferred', true)
            ->pluck('student_profile_id')
            ->unique();
        $transfers = $movements->where('movement_type', 'cambio_curso');
        $reentries = $movements->where('movement_type', 'reingreso');

        $capacity = $courseSections->sum(fn (CourseSection $course) => max(0, (int) $course->capacity));
        $courseCountWithStudents = max(1, $roster->pluck('course_section_id')->unique()->count());
        $pieStudents = $roster->filter(fn (StudentEnrollment $enrollment) => (bool) $enrollment->studentProfile?->is_pie_participant)->count();
        $completenessRate = $qualityScores->isEmpty() ? 0 : round((float) $qualityScores->avg(), 1);
        $registeredCount = $enrollments->count();

        return [
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'academic_year' => $academicYear?->only(['id', 'name', 'year', 'starts_at', 'ends_at', 'is_active', 'is_closed']),
                'date_range' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                    'period' => $filters['period'] ?? 'academic_year',
                ],
                'applied_filters' => $this->appliedFilters($filters),
                'capabilities' => [
                    'infirmary_statistics' => $includeInfirmary,
                ],
                'cache_ttl_seconds' => self::CACHE_TTL_SECONDS,
            ],
            'catalogs' => $this->catalogs($academicYear),
            'summary' => [
                'registered_students' => $registeredCount,
                'active_enrollments' => $roster->count(),
                'new_enrollments' => $newEnrollments->count(),
                'withdrawals' => $withdrawalStudentIds->count(),
                'withdrawals_without_effective_date' => $inferredWithdrawalStudentIds->count(),
                'transfers' => $transfers->count(),
                'reentries' => $reentries->count(),
                'pie_students' => $pieStudents,
                'pie_rate' => $roster->isEmpty() ? 0 : round(($pieStudents / $roster->count()) * 100, 1),
                'retention_rate' => $registeredCount === 0 ? 0 : round(($roster->count() / $registeredCount) * 100, 1),
                'average_course_size' => round($roster->count() / $courseCountWithStudents, 1),
                'occupancy_rate' => $capacity === 0 ? 0 : round(($roster->count() / $capacity) * 100, 1),
                'completeness_rate' => $completenessRate,
                'promoted_students' => $promotions->where('promotion_status', 'promovida')->count(),
                'repeating_students' => $promotions->where('promotion_status', 'repitente')->count(),
            ],
            'trends' => $this->trend($from, $to, $newEnrollments, $movements, $withdrawalEvents),
            'distributions' => [
                'by_course' => $this->courseDistribution($courseSections, $roster),
                'by_level' => $this->countBy($roster, fn (StudentEnrollment $enrollment) => $enrollment->courseSection?->educationLevel?->name ?: 'Sin nivel'),
                'by_enrollment_status' => $this->countBy($enrollments, fn (StudentEnrollment $enrollment) => $enrollment->enrollment_status ?: 'sin_estado'),
                'by_general_status' => $this->countBy($enrollments, fn (StudentEnrollment $enrollment) => $enrollment->studentProfile?->general_status ?: 'sin_estado'),
                'by_age' => $this->ageDistribution($roster, $to),
                'by_nationality' => $this->countBy($roster, fn (StudentEnrollment $enrollment) => $enrollment->studentProfile?->nationality ?: 'Sin información'),
                'by_commune' => $this->countBy($roster, fn (StudentEnrollment $enrollment) => $enrollment->studentProfile?->commune ?: 'Sin información'),
                'promotion_status' => $this->countBy($promotions, fn (StudentPromotion $promotion) => $promotion->promotion_status ?: 'sin_estado'),
                'support' => $this->supportDistribution($roster),
                'data_quality' => $this->qualityDistribution($qualityScores),
                'ethnicity' => $this->profileCountBy($profiles, fn (StudentProfile $student) => $student->ethnicity ?: 'Sin información'),
                'religion' => [
                    'affiliations' => $this->profileCountBy($profiles, fn (StudentProfile $student) => $student->religion ?: 'Sin información'),
                    'class_acceptance' => $this->booleanProfileDistribution($profiles, fn (StudentProfile $student) => $student->accepts_religion_classes),
                ],
                'family' => $this->familyStatistics($profiles),
                'health' => $this->healthStatistics($profiles),
                'infirmary' => $includeInfirmary
                    ? $this->infirmaryStatistics($studentIds, $from, $to)
                    : null,
            ],
            'details' => $includeDetails ? $this->detailRows($enrollments, $qualityScores, $to) : [],
        ];
    }

    public function details(array $filters): array
    {
        $academicYear = $this->resolveAcademicYear($filters['academic_year_id'] ?? null);
        [, $referenceDate] = $this->resolveRange($academicYear, $filters);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(500, max(10, (int) ($filters['per_page'] ?? 15)));
        $query = $this->enrollmentQuery($academicYear, $filters, false);

        if ($search = trim((string) ($filters['detail_search'] ?? ''))) {
            $query->where(function (Builder $detailQuery) use ($search) {
                $detailQuery
                    ->where('snapshot_course_display_name', 'like', "%{$search}%")
                    ->orWhereHas('studentProfile', function (Builder $studentQuery) use ($search) {
                        $studentQuery
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('registered_name', 'like', "%{$search}%")
                            ->orWhere('rut', 'like', "%{$search}%")
                            ->orWhere('commune', 'like', "%{$search}%")
                            ->orWhere('nationality', 'like', "%{$search}%");
                    });
            });
        }

        $this->applyDetailOrder(
            $query,
            (string) ($filters['sort'] ?? 'course'),
            (string) ($filters['direction'] ?? 'asc'),
        );

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $enrollments = collect($paginator->items());
        $roster = $enrollments->reject(fn (StudentEnrollment $enrollment) => in_array(
            $enrollment->enrollment_status,
            StudentEnrollment::NON_ROSTER_STATUS_VALUES,
            true,
        ));

        return [
            'data' => $this->detailRows($enrollments, $this->qualityScores($roster), $referenceDate),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    public function missingData(array $filters, bool $includeInfirmary = false): array
    {
        $dimension = (string) $filters['dimension'];
        unset($filters['dimension'], $filters['refresh']);

        $academicYear = $this->resolveAcademicYear($filters['academic_year_id'] ?? null);
        [$from, $to] = $this->resolveRange($academicYear, $filters);
        $enrollments = $this->enrollmentQuery($academicYear, $filters)->get();
        $roster = $enrollments
            ->reject(fn (StudentEnrollment $enrollment) => in_array(
                $enrollment->enrollment_status,
                StudentEnrollment::NON_ROSTER_STATUS_VALUES,
                true,
            ))
            ->values();
        $definition = $this->missingDataDefinition($dimension);

        if (($definition['source'] ?? null) === 'infirmary') {
            $rows = $includeInfirmary
                ? $this->missingInfirmaryRows($enrollments->pluck('student_profile_id')->unique(), $from, $to)
                : collect();
        } elseif (($definition['source'] ?? null) === 'none') {
            $rows = collect();
        } else {
            $source = ($definition['source'] ?? 'roster') === 'enrollments' ? $enrollments : $roster;
            $fields = collect($definition['fields'] ?? []);
            $matchAll = ($definition['match'] ?? 'any') === 'all';

            $rows = $source
                ->map(function (StudentEnrollment $enrollment) use ($fields, $matchAll) {
                    $student = $enrollment->studentProfile;

                    if (! $student) {
                        return null;
                    }

                    $missingFields = $fields
                        ->filter(fn (array $field) => $this->fieldDefinitionIsMissing($field, $enrollment, $student))
                        ->pluck('label')
                        ->values();
                    $isMissing = $matchAll
                        ? $fields->isNotEmpty() && $missingFields->count() === $fields->count()
                        : $missingFields->isNotEmpty();

                    return $isMissing
                        ? $this->missingDataRow($enrollment, $student, $missingFields)
                        : null;
                })
                ->filter()
                ->unique('id')
                ->values();
        }

        return [
            'meta' => [
                'dimension' => $dimension,
                'label' => $definition['label'],
                'total' => $rows->count(),
                'date_range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            ],
            'data' => $rows,
        ];
    }

    private function missingDataDefinition(string $dimension): array
    {
        $profileField = fn (string $field, string $label) => [
            'label' => $label,
            'source' => 'profile',
            'fields' => [$field],
        ];
        $profileFields = fn (array $fields, string $label) => [
            'label' => $label,
            'source' => 'profile',
            'fields' => $fields,
        ];
        $enrollmentField = fn (string $field, string $label) => [
            'label' => $label,
            'source' => 'enrollment',
            'fields' => [$field],
        ];

        return match ($dimension) {
            'enrollment_trend' => [
                'label' => 'Nuevas matrículas',
                'source' => 'enrollments',
                'fields' => [$enrollmentField('enrolled_at', 'Fecha de matrícula')],
            ],
            'movement_trend' => [
                'label' => 'Movimientos administrativos',
                'source' => 'none',
                'fields' => [],
            ],
            'course' => [
                'label' => 'Matrícula vigente por curso',
                'fields' => [$enrollmentField('course_section_id', 'Curso')],
            ],
            'enrollment_status' => [
                'label' => 'Estado de matrícula',
                'source' => 'enrollments',
                'fields' => [$enrollmentField('enrollment_status', 'Estado de matrícula')],
            ],
            'age' => [
                'label' => 'Distribución por edad',
                'fields' => [$profileField('birthdate', 'Fecha de nacimiento')],
            ],
            'commune' => [
                'label' => 'Principales comunas',
                'fields' => [$profileField('commune', 'Comuna')],
            ],
            'support' => [
                'label' => 'Cobertura declarada',
                'fields' => [
                    $profileField('is_pie_participant', 'Participación PIE'),
                    $profileField('has_internet', 'Acceso a internet'),
                    $profileField('has_computer', 'Acceso a computador'),
                    $profileField('has_repeated_course', 'Repitencia'),
                ],
            ],
            'quality' => [
                'label' => 'Calidad de las fichas',
                'fields' => [
                    $profileField('first_name', 'Nombres'),
                    $profileField('last_name', 'Apellidos'),
                    $profileField('rut', 'RUT'),
                    $profileField('birthdate', 'Fecha de nacimiento'),
                    $profileField('address', 'Dirección'),
                    $profileField('commune', 'Comuna'),
                    $profileField('guardian_name', 'Nombre del apoderado'),
                    $profileField('guardian_phone', 'Teléfono del apoderado'),
                ],
            ],
            'ethnicity' => [
                'label' => 'Etnia declarada',
                'fields' => [$profileField('ethnicity', 'Etnia')],
            ],
            'religion' => [
                'label' => 'Religión declarada',
                'fields' => [$profileField('religion', 'Religión')],
            ],
            'student_communes' => [
                'label' => 'Comuna de la estudiante',
                'fields' => [$profileField('commune', 'Comuna de la estudiante')],
            ],
            'lives_with' => [
                'label' => 'Vive con',
                'fields' => [$profileField('lives_with', 'Con quién vive la estudiante')],
            ],
            'guardian_communes' => [
                'label' => 'Comuna del apoderado titular',
                'fields' => [$profileField('guardian_commune', 'Comuna del apoderado titular')],
            ],
            'backup_guardian_communes' => [
                'label' => 'Comuna del apoderado suplente',
                'fields' => [$profileField('guardian_backup_commune', 'Comuna del apoderado suplente')],
            ],
            'guardian_photo_authorizations' => [
                'label' => 'Autorización de fotografía o grabación del apoderado titular',
                'fields' => [$profileField('guardian_photo_authorization', 'Autorización de fotografía o grabación del apoderado titular')],
            ],
            'backup_guardian_photo_authorizations' => [
                'label' => 'Autorización de fotografía o grabación del apoderado suplente',
                'fields' => [$profileField('guardian_backup_photo_authorization', 'Autorización de fotografía o grabación del apoderado suplente')],
            ],
            'guardian_relationships' => [
                'label' => 'Parentesco del apoderado principal',
                'fields' => [$profileField('guardian_relationship', 'Parentesco del apoderado principal')],
            ],
            'backup_guardian_relationships' => [
                'label' => 'Parentesco del apoderado suplente',
                'fields' => [$profileField('guardian_backup_relationship', 'Parentesco del apoderado suplente')],
            ],
            'guardian_roles' => [
                'label' => 'Rol del apoderado principal',
                'fields' => [$profileField('guardian_role', 'Rol del apoderado principal')],
            ],
            'backup_guardian_roles' => [
                'label' => 'Rol del apoderado suplente',
                'fields' => [$profileField('guardian_backup_role', 'Rol del apoderado suplente')],
            ],
            'guardian_education_levels' => [
                'label' => 'Nivel educacional del apoderado principal',
                'fields' => [$profileFields(
                    ['guardian_last_education_level', 'guardian_education_level'],
                    'Nivel educacional del apoderado principal',
                )],
            ],
            'backup_guardian_education_levels' => [
                'label' => 'Nivel educacional del apoderado suplente',
                'fields' => [$profileFields(
                    ['guardian_backup_last_education_level', 'guardian_backup_education_level'],
                    'Nivel educacional del apoderado suplente',
                )],
            ],
            'father_education_levels' => [
                'label' => 'Nivel educacional del padre',
                'fields' => [$profileField('father_education_level', 'Nivel educacional del padre')],
            ],
            'mother_education_levels' => [
                'label' => 'Nivel educacional de la madre',
                'fields' => [$profileField('mother_education_level', 'Nivel educacional de la madre')],
            ],
            'guardian_occupations' => [
                'label' => 'Ocupación del apoderado principal',
                'fields' => [$profileField('guardian_occupation', 'Ocupación del apoderado principal')],
            ],
            'backup_guardian_occupations' => [
                'label' => 'Ocupación del apoderado suplente',
                'fields' => [$profileField('guardian_backup_occupation', 'Ocupación del apoderado suplente')],
            ],
            'father_occupations' => [
                'label' => 'Ocupación del padre',
                'fields' => [$profileField('father_occupation', 'Ocupación del padre')],
            ],
            'mother_occupations' => [
                'label' => 'Ocupación de la madre',
                'fields' => [$profileField('mother_occupation', 'Ocupación de la madre')],
            ],
            'guardian_marital_statuses' => [
                'label' => 'Estado civil del apoderado principal',
                'fields' => [$profileField('guardian_marital_status', 'Estado civil del apoderado principal')],
            ],
            'backup_guardian_marital_statuses' => [
                'label' => 'Estado civil del apoderado suplente',
                'fields' => [$profileField('guardian_backup_marital_status', 'Estado civil del apoderado suplente')],
            ],
            'parent_nationalities' => [
                'label' => 'Nacionalidad de madre y padre',
                'fields' => [
                    $profileField('father_nationality', 'Nacionalidad del padre'),
                    $profileField('mother_nationality', 'Nacionalidad de la madre'),
                ],
            ],
            'parent_presence' => [
                'label' => 'Registro de madre y padre',
                'match' => 'all',
                'fields' => [
                    $profileField('father_name', 'Nombre del padre'),
                    $profileField('mother_name', 'Nombre de la madre'),
                ],
            ],
            'health_conditions' => [
                'label' => 'Condiciones y alertas de salud',
                'fields' => [
                    $profileField('has_chronic_illness', 'Enfermedad crónica'),
                    $profileField('has_medication_allergies', 'Alergia a medicamentos'),
                    $profileField('has_physical_restrictions', 'Restricción física'),
                    $profileField('fit_for_physical_education', 'Apta para educación física'),
                    $profileField('has_private_school_insurance', 'Seguro escolar privado'),
                    $profileField('food_allergies', 'Alergia alimentaria'),
                ],
            ],
            'health_insurance' => [
                'label' => 'Previsión de salud',
                'fields' => [$profileField('health_insurance', 'Previsión de salud')],
            ],
            'infirmary_attentions' => [
                'label' => 'Atenciones por categoría',
                'source' => 'infirmary',
                'fields' => [],
            ],
        };
    }

    private function fieldDefinitionIsMissing(
        array $definition,
        StudentEnrollment $enrollment,
        StudentProfile $student,
    ): bool {
        $source = ($definition['source'] ?? 'profile') === 'enrollment' ? $enrollment : $student;

        return collect($definition['fields'] ?? [])
            ->every(fn (string $field) => blank(data_get($source, $field)));
    }

    private function missingDataRow(
        StudentEnrollment $enrollment,
        StudentProfile $student,
        Collection $missingFields,
    ): array {
        return [
            'id' => $student->id,
            'name' => trim((string) ($student->registered_name ?: $student->full_name)),
            'rut' => $student->rut,
            'course' => $enrollment->snapshot_course_display_name ?: $enrollment->courseSection?->display_name,
            'missing_fields' => $missingFields->values()->all(),
        ];
    }

    private function missingInfirmaryRows(Collection $studentIds, Carbon $from, Carbon $to): Collection
    {
        if ($studentIds->isEmpty()) {
            return collect();
        }

        return InfirmaryAttention::query()
            ->with('student:id,first_name,last_name,registered_name,rut')
            ->whereIn('student_profile_id', $studentIds)
            ->whereBetween('attended_at', [$from, $to])
            ->where(fn (Builder $query) => $query
                ->whereNull('attention_category')
                ->orWhere('attention_category', ''))
            ->orderBy('course_name_snapshot')
            ->get()
            ->map(fn (InfirmaryAttention $attention) => [
                'id' => $attention->student_profile_id,
                'name' => trim((string) ($attention->student?->registered_name ?: $attention->student?->full_name ?: $attention->student_full_name_snapshot)),
                'rut' => $attention->student?->rut ?: $attention->student_rut_snapshot,
                'course' => $attention->course_name_snapshot,
                'missing_fields' => ['Categoría de atención'],
            ])
            ->unique('id')
            ->values();
    }

    private function summaryCacheKey(array $filters, bool $includeInfirmary): string
    {
        ksort($filters);

        return 'student-reports:summary:v4:'.sha1(json_encode([
            'filters' => $filters,
            'infirmary' => $includeInfirmary,
        ], JSON_THROW_ON_ERROR));
    }

    private function resolveAcademicYear(?int $academicYearId): ?AcademicYear
    {
        if ($academicYearId) {
            return AcademicYear::query()->find($academicYearId);
        }

        return AcademicYear::query()->where('is_active', true)->first()
            ?? AcademicYear::query()->ordered()->first();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function resolveRange(?AcademicYear $academicYear, array $filters): array
    {
        $year = (int) ($academicYear?->year ?: now()->year);
        $yearStart = $academicYear?->starts_at
            ? Carbon::parse($academicYear->starts_at)->startOfDay()
            : Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = $academicYear?->ends_at
            ? Carbon::parse($academicYear->ends_at)->endOfDay()
            : Carbon::create($year, 12, 31)->endOfDay();

        return match ($filters['period'] ?? 'academic_year') {
            'semester_1' => [$yearStart, Carbon::create($year, 6, 30)->endOfDay()->min($yearEnd)],
            'semester_2' => [Carbon::create($year, 7, 1)->startOfDay()->max($yearStart), $yearEnd],
            'month' => $this->monthRange((string) ($filters['month'] ?? $yearStart->format('Y-m')), $yearStart, $yearEnd),
            'custom' => [
                isset($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : $yearStart,
                isset($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : $yearEnd,
            ],
            default => [$yearStart, $yearEnd],
        };
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function monthRange(string $month, Carbon $yearStart, Carbon $yearEnd): array
    {
        $requestedDate = preg_match('/^\d{4}-\d{2}$/', $month) ? Carbon::createFromFormat('Y-m', $month) : $yearStart->copy();
        $date = Carbon::create($yearStart->year, $requestedDate->month, 1);

        return [
            $date->copy()->startOfMonth()->max($yearStart),
            $date->copy()->endOfMonth()->min($yearEnd),
        ];
    }

    private function enrollmentQuery(?AcademicYear $academicYear, array $filters, bool $withDefaultOrder = true): Builder
    {
        $query = StudentEnrollment::query()
            ->with([
                'studentProfile:id,first_name,last_name,registered_name,rut,birthdate,nationality,commune,general_status,is_pie_participant,has_internet,has_computer,has_repeated_course,address,guardian_name,guardian_phone,ethnicity,religion,accepts_religion_classes,guardian_relationship,guardian_role,guardian_commune,guardian_photo_authorization,guardian_marital_status,guardian_education_level,guardian_last_education_level,guardian_occupation,guardian_backup_name,guardian_backup_relationship,guardian_backup_role,guardian_backup_commune,guardian_backup_photo_authorization,guardian_backup_marital_status,guardian_backup_education_level,guardian_backup_last_education_level,guardian_backup_occupation,lives_with,father_name,father_nationality,father_occupation,father_education_level,mother_name,mother_nationality,mother_occupation,mother_education_level,health_insurance,blood_type,healthcare_provider,food_allergies,has_chronic_illness,has_medication_allergies,has_physical_restrictions,fit_for_physical_education,has_private_school_insurance',
                'academicYear:id,name,year,is_active,is_closed',
                'courseSection:id,academic_year_id,education_level_id,section_name,display_name,capacity,active',
                'courseSection.educationLevel:id,name,order,type',
            ])
            ->when($academicYear, fn (Builder $query) => $query->where('academic_year_id', $academicYear->id))
            ->when($filters['course_section_id'] ?? null, fn (Builder $query, $id) => $query->where('course_section_id', $id))
            ->when($filters['education_level_id'] ?? null, fn (Builder $query, $id) => $query->whereHas(
                'courseSection',
                fn (Builder $courseQuery) => $courseQuery->where('education_level_id', $id)
            ))
            ->when($filters['enrollment_status'] ?? null, fn (Builder $query, $status) => $query->where('enrollment_status', $status))
            ->whereHas('studentProfile', fn (Builder $query) => $this->applyStudentFilters($query, $filters));

        if ($withDefaultOrder) {
            $query->orderBy('snapshot_course_display_name')->orderBy('student_profile_id');
        }

        return $query;
    }

    private function applyDetailOrder(Builder $query, string $sort, string $direction): void
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        if ($sort === 'name') {
            $query
                ->orderBy(
                    StudentProfile::query()
                        ->select('last_name')
                        ->whereColumn('student_profiles.id', 'student_enrollments.student_profile_id')
                        ->limit(1),
                    $direction,
                )
                ->orderBy(
                    StudentProfile::query()
                        ->select('first_name')
                        ->whereColumn('student_profiles.id', 'student_enrollments.student_profile_id')
                        ->limit(1),
                    $direction,
                );
        } else {
            $query->orderBy('snapshot_course_display_name', $direction);
        }

        $query->orderBy('student_profile_id', $direction);
    }

    private function movementQuery(
        ?AcademicYear $academicYear,
        array $filters,
        Collection $studentIds,
        Carbon $from,
        Carbon $to,
    ): Builder {
        return StudentEnrollmentMovement::query()
            ->when($academicYear, fn (Builder $query) => $query->where('academic_year_id', $academicYear->id))
            ->whereBetween('effective_date', [$from->toDateString(), $to->toDateString()])
            ->when($studentIds->isNotEmpty(), fn (Builder $query) => $query->whereIn('student_profile_id', $studentIds))
            ->when($studentIds->isEmpty(), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($filters['course_section_id'] ?? null, function (Builder $query, $courseId) {
                $query->where(fn (Builder $inner) => $inner
                    ->where('from_course_section_id', $courseId)
                    ->orWhere('to_course_section_id', $courseId));
            })
            ->when($filters['education_level_id'] ?? null, function (Builder $query, $levelId) {
                $query->where(function (Builder $inner) use ($levelId) {
                    $inner
                        ->whereHas('fromCourseSection', fn (Builder $courseQuery) => $courseQuery->where('education_level_id', $levelId))
                        ->orWhereHas('toCourseSection', fn (Builder $courseQuery) => $courseQuery->where('education_level_id', $levelId));
                });
            })
            ->orderBy('effective_date');
    }

    private function promotionQuery(?AcademicYear $academicYear, array $filters, Collection $studentIds): Builder
    {
        return StudentPromotion::query()
            ->when($academicYear, fn (Builder $query) => $query->where('from_academic_year_id', $academicYear->id))
            ->when($studentIds->isNotEmpty(), fn (Builder $query) => $query->whereIn('student_profile_id', $studentIds))
            ->when($studentIds->isEmpty(), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($filters['course_section_id'] ?? null, fn (Builder $query, $id) => $query->where('from_course_section_id', $id))
            ->when($filters['education_level_id'] ?? null, fn (Builder $query, $id) => $query->whereHas(
                'fromCourseSection',
                fn (Builder $courseQuery) => $courseQuery->where('education_level_id', $id)
            ));
    }

    private function applyStudentFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['general_status'] ?? null, fn (Builder $inner, $status) => $inner->where('general_status', $status))
            ->when(array_key_exists('is_pie_participant', $filters) && $filters['is_pie_participant'] !== null, fn (Builder $inner) => $inner->where('is_pie_participant', (bool) $filters['is_pie_participant']))
            ->when($filters['nationality'] ?? null, fn (Builder $inner, $nationality) => $inner->where('nationality', $nationality))
            ->when($filters['commune'] ?? null, fn (Builder $inner, $commune) => $inner->where('commune', $commune))
            ->when($filters['search'] ?? null, function (Builder $inner, $search) {
                $inner->where(function (Builder $searchQuery) use ($search) {
                    $searchQuery
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('registered_name', 'like', "%{$search}%")
                        ->orWhere('rut', 'like', "%{$search}%");
                });
            });
    }

    private function courseSections(?AcademicYear $academicYear, array $filters): Collection
    {
        return CourseSection::query()
            ->with('educationLevel:id,name,order,type')
            ->when($academicYear, fn (Builder $query) => $query->where('academic_year_id', $academicYear->id))
            ->when($filters['education_level_id'] ?? null, fn (Builder $query, $id) => $query->where('education_level_id', $id))
            ->when($filters['course_section_id'] ?? null, fn (Builder $query, $id) => $query->whereKey($id))
            ->orderBy('education_level_id')
            ->orderBy('section_name')
            ->get(['id', 'academic_year_id', 'education_level_id', 'section_name', 'display_name', 'capacity', 'active']);
    }

    private function catalogs(?AcademicYear $academicYear): array
    {
        return [
            'academic_years' => AcademicYear::query()->ordered()->get(['id', 'name', 'year', 'is_active', 'is_closed']),
            'education_levels' => EducationLevel::query()->orderBy('order')->get(['id', 'name', 'order', 'type']),
            'courses' => CourseSection::query()
                ->when($academicYear, fn (Builder $query) => $query->where('academic_year_id', $academicYear->id))
                ->orderBy('education_level_id')
                ->orderBy('section_name')
                ->get(['id', 'academic_year_id', 'education_level_id', 'display_name', 'capacity', 'active']),
            'general_statuses' => StudentProfile::GENERAL_STATUS_OPTIONS,
            'enrollment_statuses' => StudentEnrollment::STATUS_OPTIONS,
            'nationalities' => StudentProfile::query()->whereNotNull('nationality')->where('nationality', '!=', '')->distinct()->orderBy('nationality')->pluck('nationality'),
            'communes' => StudentProfile::query()->whereNotNull('commune')->where('commune', '!=', '')->distinct()->orderBy('commune')->pluck('commune'),
        ];
    }

    private function appliedFilters(array $filters): array
    {
        return collect($filters)
            ->except(['period', 'month', 'from', 'to'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    private function dateIsWithin(mixed $value, Carbon $from, Carbon $to): bool
    {
        if (! $value) {
            return false;
        }

        return Carbon::parse($value)->betweenIncluded($from, $to);
    }

    private function withdrawalEvents(
        Collection $enrollments,
        Collection $movements,
        Carbon $from,
        Carbon $to,
    ): Collection {
        $movementEvents = $movements
            ->where('movement_type', 'retiro')
            ->map(fn (StudentEnrollmentMovement $movement) => [
                'student_profile_id' => $movement->student_profile_id,
                'date' => $movement->effective_date,
                'date_inferred' => false,
            ]);

        $enrollmentIds = $enrollments->pluck('id')->filter()->values();
        $enrollmentIdsWithWithdrawalMovement = $enrollmentIds->isEmpty()
            ? collect()
            : StudentEnrollmentMovement::query()
                ->whereIn('student_enrollment_id', $enrollmentIds)
                ->where('movement_type', 'retiro')
                ->pluck('student_enrollment_id');

        $statusEvents = $enrollments
            ->where('enrollment_status', 'retirada')
            ->reject(fn (StudentEnrollment $enrollment) => $enrollmentIdsWithWithdrawalMovement->contains($enrollment->id))
            ->map(function (StudentEnrollment $enrollment) {
                $dateInferred = ! $enrollment->withdrawn_at;

                return [
                    'student_profile_id' => $enrollment->student_profile_id,
                    'date' => $enrollment->withdrawn_at
                        ?? $enrollment->updated_at
                        ?? $enrollment->created_at
                        ?? $enrollment->enrolled_at,
                    'date_inferred' => $dateInferred,
                ];
            })
            ->filter(fn (array $event) => $this->dateIsWithin($event['date'], $from, $to));

        return $movementEvents
            ->concat($statusEvents)
            ->filter(fn (array $event) => $event['student_profile_id'] && $event['date'])
            ->values();
    }

    private function trend(
        Carbon $from,
        Carbon $to,
        Collection $newEnrollments,
        Collection $movements,
        Collection $withdrawalEvents,
    ): array
    {
        $months = collect();
        $cursor = $from->copy()->startOfMonth();
        $lastMonth = $to->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($lastMonth)) {
            $months->push($cursor->format('Y-m'));
            $cursor->addMonth();
        }

        $series = [
            'enrollments' => $this->monthlyCounts($months, $newEnrollments, fn (StudentEnrollment $enrollment) => $enrollment->enrolled_at),
            'withdrawals' => $this->monthlyCounts($months, $withdrawalEvents, fn (array $event) => $event['date']),
            'transfers' => $this->monthlyCounts($months, $movements->where('movement_type', 'cambio_curso'), fn (StudentEnrollmentMovement $movement) => $movement->effective_date),
            'reentries' => $this->monthlyCounts($months, $movements->where('movement_type', 'reingreso'), fn (StudentEnrollmentMovement $movement) => $movement->effective_date),
        ];

        return ['categories' => $months->values(), 'series' => $series];
    }

    private function monthlyCounts(Collection $months, Collection $items, callable $dateResolver): Collection
    {
        $counts = $items
            ->filter(fn ($item) => $dateResolver($item))
            ->countBy(fn ($item) => Carbon::parse($dateResolver($item))->format('Y-m'));

        return $months->map(fn (string $month) => (int) $counts->get($month, 0))->values();
    }

    private function courseDistribution(Collection $courses, Collection $roster): Collection
    {
        $counts = $roster->countBy('course_section_id');

        return $courses->map(function (CourseSection $course) use ($counts) {
            $total = (int) $counts->get($course->id, 0);
            $capacity = max(0, (int) $course->capacity);

            return [
                'id' => $course->id,
                'label' => $course->display_name,
                'level' => $course->educationLevel?->name,
                'total' => $total,
                'capacity' => $capacity,
                'available_places' => max(0, $capacity - $total),
                'occupancy_rate' => $capacity === 0 ? 0 : round(($total / $capacity) * 100, 1),
            ];
        })->values();
    }

    private function countBy(Collection $items, callable $labelResolver): Collection
    {
        return $items
            ->countBy($labelResolver)
            ->map(fn (int $total, string $label) => ['label' => $label, 'total' => $total])
            ->sortByDesc('total')
            ->values();
    }

    private function ageDistribution(Collection $roster, Carbon $referenceDate): Collection
    {
        $groups = ['Sin información' => 0, '4-5 años' => 0, '6-9 años' => 0, '10-13 años' => 0, '14-17 años' => 0, '18+ años' => 0];

        foreach ($roster as $enrollment) {
            $birthdate = $enrollment->studentProfile?->birthdate;

            if (! $birthdate) {
                $groups['Sin información']++;

                continue;
            }

            $age = (int) Carbon::parse($birthdate)->diffInYears($referenceDate);
            $label = match (true) {
                $age <= 5 => '4-5 años',
                $age <= 9 => '6-9 años',
                $age <= 13 => '10-13 años',
                $age <= 17 => '14-17 años',
                default => '18+ años',
            };
            $groups[$label]++;
        }

        return collect($groups)
            ->map(fn (int $total, string $label) => ['label' => $label, 'total' => $total])
            ->values();
    }

    private function supportDistribution(Collection $roster): array
    {
        return [
            'pie' => $this->booleanDistribution($roster, fn (StudentEnrollment $enrollment) => $enrollment->studentProfile?->is_pie_participant),
            'internet' => $this->booleanDistribution($roster, fn (StudentEnrollment $enrollment) => $enrollment->studentProfile?->has_internet),
            'computer' => $this->booleanDistribution($roster, fn (StudentEnrollment $enrollment) => $enrollment->studentProfile?->has_computer),
            'repeated_course' => $this->booleanDistribution($roster, fn (StudentEnrollment $enrollment) => $enrollment->studentProfile?->has_repeated_course),
        ];
    }

    private function booleanDistribution(Collection $items, callable $valueResolver): array
    {
        $counts = ['yes' => 0, 'no' => 0, 'unknown' => 0];

        foreach ($items as $item) {
            $value = $valueResolver($item);
            $counts[$value === null ? 'unknown' : ($value ? 'yes' : 'no')]++;
        }

        return $counts;
    }

    private function profileCountBy(Collection $profiles, callable $labelResolver): Collection
    {
        return $profiles
            ->countBy(fn (StudentProfile $student) => trim((string) ($labelResolver($student) ?: 'Sin información')))
            ->map(fn (int $total, string $label) => ['label' => $label, 'total' => $total])
            ->sortByDesc('total')
            ->values();
    }

    private function booleanProfileDistribution(Collection $profiles, callable $valueResolver): array
    {
        return $this->booleanDistribution($profiles, $valueResolver);
    }

    private function booleanProfileDistributionRows(Collection $profiles, callable $valueResolver): Collection
    {
        $counts = $this->booleanProfileDistribution($profiles, $valueResolver);

        return collect([
            ['label' => 'Autoriza', 'total' => $counts['yes']],
            ['label' => 'No autoriza', 'total' => $counts['no']],
            ['label' => 'Sin información', 'total' => $counts['unknown']],
        ]);
    }

    private function familyStatistics(Collection $profiles): array
    {
        $parentNationalities = $profiles
            ->flatMap(fn (StudentProfile $student) => [$student->father_nationality, $student->mother_nationality])
            ->filter(fn ($value) => filled($value))
            ->values();

        return [
            'student_communes' => $this->profileCountBy($profiles, fn (StudentProfile $student) => $student->commune ?: 'Sin información'),
            'lives_with' => $this->profileCountBy($profiles, fn (StudentProfile $student) => $student->lives_with ?: 'Sin información'),
            'guardian_communes' => $this->profileCountBy($profiles, fn (StudentProfile $student) => $student->guardian_commune ?: 'Sin información'),
            'backup_guardian_communes' => $this->profileCountBy($profiles, fn (StudentProfile $student) => $student->guardian_backup_commune ?: 'Sin información'),
            'guardian_photo_authorizations' => $this->booleanProfileDistributionRows(
                $profiles,
                fn (StudentProfile $student) => $student->guardian_photo_authorization,
            ),
            'backup_guardian_photo_authorizations' => $this->booleanProfileDistributionRows(
                $profiles,
                fn (StudentProfile $student) => $student->guardian_backup_photo_authorization,
            ),
            'guardian_relationships' => $this->profileCountBy($profiles, fn (StudentProfile $student) => $student->guardian_relationship ?: 'Sin información'),
            'backup_guardian_relationships' => $this->profileCountBy($profiles, fn (StudentProfile $student) => $student->guardian_backup_relationship ?: 'Sin información'),
            'guardian_roles' => $this->profileCountBy($profiles, fn (StudentProfile $student) => $student->guardian_role ?: 'Sin información'),
            'backup_guardian_roles' => $this->profileCountBy($profiles, fn (StudentProfile $student) => $student->guardian_backup_role ?: 'Sin información'),
            'guardian_occupations' => $this->profileCountBy($profiles, fn (StudentProfile $student) => $student->guardian_occupation ?: 'Sin información'),
            'backup_guardian_occupations' => $this->profileCountBy($profiles, fn (StudentProfile $student) => $student->guardian_backup_occupation ?: 'Sin información'),
            'father_occupations' => $this->profileCountBy($profiles, fn (StudentProfile $student) => $student->father_occupation ?: 'Sin información'),
            'mother_occupations' => $this->profileCountBy($profiles, fn (StudentProfile $student) => $student->mother_occupation ?: 'Sin información'),
            'guardian_marital_statuses' => $this->profileCountBy($profiles, fn (StudentProfile $student) => $student->guardian_marital_status ?: 'Sin información'),
            'backup_guardian_marital_statuses' => $this->profileCountBy($profiles, fn (StudentProfile $student) => $student->guardian_backup_marital_status ?: 'Sin información'),
            'guardian_education_levels' => $this->profileCountBy($profiles, fn (StudentProfile $student) => $student->guardian_last_education_level ?: $student->guardian_education_level ?: 'Sin información'),
            'backup_guardian_education_levels' => $this->profileCountBy($profiles, fn (StudentProfile $student) => $student->guardian_backup_last_education_level ?: $student->guardian_backup_education_level ?: 'Sin información'),
            'father_education_levels' => $this->profileCountBy($profiles, fn (StudentProfile $student) => $student->father_education_level ?: 'Sin información'),
            'mother_education_levels' => $this->profileCountBy($profiles, fn (StudentProfile $student) => $student->mother_education_level ?: 'Sin información'),
            'parent_presence' => $this->profileCountBy($profiles, function (StudentProfile $student) {
                $hasFather = filled($student->father_name);
                $hasMother = filled($student->mother_name);

                return match (true) {
                    $hasFather && $hasMother => 'Madre y padre registrados',
                    $hasFather => 'Solo padre registrado',
                    $hasMother => 'Solo madre registrada',
                    default => 'Sin información de padres',
                };
            }),
            'parent_nationalities' => $this->countValues($parentNationalities),
        ];
    }

    private function healthStatistics(Collection $profiles): array
    {
        return [
            'insurance' => $this->profileCountBy($profiles, fn (StudentProfile $student) => $student->health_insurance ?: 'Sin información'),
            'blood_type' => $this->profileCountBy($profiles, fn (StudentProfile $student) => $student->blood_type ?: 'Sin información'),
            'healthcare_provider' => $this->profileCountBy($profiles, fn (StudentProfile $student) => $student->healthcare_provider ?: 'Sin información'),
            'conditions' => [
                $this->healthCondition($profiles, 'chronic_illness', 'Enfermedad crónica', fn (StudentProfile $student) => $student->has_chronic_illness),
                $this->healthCondition($profiles, 'medication_allergies', 'Alergia a medicamentos', fn (StudentProfile $student) => $student->has_medication_allergies),
                $this->healthCondition($profiles, 'physical_restrictions', 'Restricción física', fn (StudentProfile $student) => $student->has_physical_restrictions),
                $this->healthCondition($profiles, 'physical_education', 'Apta para educación física', fn (StudentProfile $student) => $student->fit_for_physical_education),
                $this->healthCondition($profiles, 'private_insurance', 'Seguro escolar privado', fn (StudentProfile $student) => $student->has_private_school_insurance),
                $this->healthCondition(
                    $profiles,
                    'food_allergies',
                    'Alergia alimentaria registrada',
                    fn (StudentProfile $student) => filled($student->food_allergies) ? true : null,
                ),
            ],
        ];
    }

    private function healthCondition(Collection $profiles, string $key, string $label, callable $valueResolver): array
    {
        return [
            'key' => $key,
            'label' => $label,
            ...$this->booleanProfileDistribution($profiles, $valueResolver),
        ];
    }

    private function infirmaryStatistics(Collection $studentIds, Carbon $from, Carbon $to): array
    {
        if ($studentIds->isEmpty()) {
            return $this->emptyInfirmaryStatistics();
        }

        $attentions = InfirmaryAttention::query()
            ->whereIn('student_profile_id', $studentIds)
            ->whereBetween('attended_at', [$from, $to]);
        $accidents = InfirmaryAccident::query()
            ->whereIn('student_profile_id', $studentIds)
            ->whereBetween('occurred_at', [$from, $to]);
        $administrations = InfirmaryMedicationAdministration::query()
            ->whereIn('student_profile_id', $studentIds)
            ->whereBetween('administered_at', [$from, $to])
            ->where('administration_status', InfirmaryMedicationAdministration::STATUS_ADMINISTRADA);
        $referrals = InfirmaryAttentionReferral::query()
            ->whereBetween('referred_at', [$from, $to])
            ->whereHas('attention', fn (Builder $query) => $query->whereIn('student_profile_id', $studentIds));
        $studentsAttended = (clone $attentions)->distinct()->count('student_profile_id');
        $attentionCount = (clone $attentions)->count();

        return [
            'summary' => [
                'students_attended' => $studentsAttended,
                'attentions' => $attentionCount,
                'accidents' => (clone $accidents)->count(),
                'medication_administrations' => (clone $administrations)->count(),
                'referrals' => (clone $referrals)->count(),
                'average_attentions_per_student' => $studentsAttended === 0
                    ? 0
                    : round($attentionCount / $studentsAttended, 1),
            ],
            'attentions_by_category' => $this->groupedCounts($attentions, 'attention_category'),
            'attentions_by_priority' => $this->groupedCounts($attentions, 'priority'),
            'accidents_by_type' => $this->groupedCounts($accidents, 'accident_type'),
            'accidents_by_severity' => $this->groupedCounts($accidents, 'severity'),
            'referrals_by_type' => $this->groupedCounts($referrals, 'referral_type'),
        ];
    }

    private function groupedCounts(Builder $query, string $column): Collection
    {
        return (clone $query)
            ->select($column)
            ->selectRaw('COUNT(*) as total')
            ->groupBy($column)
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => trim((string) ($row->{$column} ?: 'Sin información')),
                'total' => (int) $row->total,
            ])
            ->values();
    }

    private function emptyInfirmaryStatistics(): array
    {
        return [
            'summary' => [
                'students_attended' => 0,
                'attentions' => 0,
                'accidents' => 0,
                'medication_administrations' => 0,
                'referrals' => 0,
                'average_attentions_per_student' => 0,
            ],
            'attentions_by_category' => [],
            'attentions_by_priority' => [],
            'accidents_by_type' => [],
            'accidents_by_severity' => [],
            'referrals_by_type' => [],
        ];
    }

    private function countValues(Collection $values): Collection
    {
        return $values
            ->map(fn ($value) => trim((string) ($value ?: 'Sin información')))
            ->countBy()
            ->map(fn (int $total, string $label) => ['label' => $label, 'total' => $total])
            ->sortByDesc('total')
            ->values();
    }

    private function qualityScores(Collection $roster): Collection
    {
        return $roster->mapWithKeys(function (StudentEnrollment $enrollment) {
            $student = $enrollment->studentProfile;
            $completed = collect(self::QUALITY_FIELDS)
                ->filter(fn (string $field) => filled($student?->{$field}))
                ->count();

            return [$enrollment->student_profile_id => (int) round(($completed / count(self::QUALITY_FIELDS)) * 100)];
        });
    }

    private function qualityDistribution(Collection $scores): array
    {
        return [
            ['label' => 'Completa', 'total' => $scores->filter(fn (int $score) => $score >= 90)->count()],
            ['label' => 'Parcial', 'total' => $scores->filter(fn (int $score) => $score >= 65 && $score < 90)->count()],
            ['label' => 'Crítica', 'total' => $scores->filter(fn (int $score) => $score < 65)->count()],
        ];
    }

    private function detailRows(Collection $enrollments, Collection $qualityScores, Carbon $referenceDate): Collection
    {
        return $enrollments->map(function (StudentEnrollment $enrollment) use ($qualityScores, $referenceDate) {
            $student = $enrollment->studentProfile;
            $birthdate = $student?->birthdate;

            return [
                'id' => $student?->id,
                'name' => $student?->registered_name_resolved ?: $student?->full_name,
                'rut' => $student?->rut,
                'course' => $enrollment->courseSection?->display_name ?: $enrollment->snapshot_course_display_name,
                'course_section_id' => $enrollment->course_section_id,
                'level' => $enrollment->courseSection?->educationLevel?->name ?: $enrollment->snapshot_level_name,
                'enrollment_status' => $enrollment->enrollment_status,
                'general_status' => $student?->general_status,
                'age' => $birthdate ? (int) Carbon::parse($birthdate)->diffInYears($referenceDate) : null,
                'nationality' => $student?->nationality,
                'commune' => $student?->commune,
                'is_pie_participant' => (bool) $student?->is_pie_participant,
                'enrolled_at' => $enrollment->enrolled_at?->format('Y-m-d'),
                'withdrawn_at' => $enrollment->withdrawn_at?->format('Y-m-d'),
                'quality_score' => (int) $qualityScores->get($student?->id, 0),
            ];
        })->values();
    }
}
