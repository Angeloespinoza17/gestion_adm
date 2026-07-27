<?php

namespace App\Services\Infirmary;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\Infirmary\InfirmaryAccident;
use App\Models\Infirmary\InfirmaryAttention;
use App\Models\Infirmary\InfirmaryMedicationAdministration;
use App\Models\Infirmary\InfirmaryMedicationAuthorization;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class InfirmaryStudentContextService
{
    private ?Collection $teachers = null;

    public function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    public function currentEnrollment(StudentProfile $student, ?AcademicYear $academicYear = null): ?StudentEnrollment
    {
        $year = $academicYear ?: $this->activeAcademicYear();

        if (!$year) {
            return null;
        }

        $student->loadMissing([
            'enrollments.academicYear:id,name,year,is_active',
            'enrollments.courseSection:id,academic_year_id,education_level_id,section_name,display_name',
            'enrollments.courseSection.educationLevel:id,name,order,type',
        ]);

        return $student->preferredEnrollment($year);
    }

    public function teacherForCourse(?CourseSection $courseSection): ?Staff
    {
        if (!$courseSection) {
            return $this->teachers()->first();
        }

        $teachers = $this->teachers();
        if ($teachers->isEmpty()) {
            return null;
        }

        $index = ((int) $courseSection->id) % $teachers->count();

        return $teachers->values()->get($index) ?: $teachers->first();
    }

    public function ageAt(StudentProfile $student, Carbon|string|null $date = null): ?int
    {
        if (!$student->birthdate) {
            return null;
        }

        $reference = $date instanceof Carbon
            ? $date
            : ($date ? Carbon::parse($date) : now());

        return (int) Carbon::parse($student->birthdate)->diffInYears($reference);
    }

    public function studentSummary(StudentProfile $student, Carbon|string|null $date = null): array
    {
        $reference = $date instanceof Carbon
            ? $date
            : ($date ? Carbon::parse($date) : now());

        $enrollment = $this->currentEnrollment($student);
        $teacher = $this->teacherForCourse($enrollment?->courseSection);
        $permanentMedications = InfirmaryMedicationAuthorization::query()
            ->with('medication:id,name,commercial_name')
            ->where('student_profile_id', $student->id)
            ->whereIn('status', [
                InfirmaryMedicationAuthorization::STATUS_VIGENTE,
                InfirmaryMedicationAuthorization::STATUS_PROXIMA_A_VENCER,
            ])
            ->orderBy('start_date')
            ->get();

        return [
            'id' => $student->id,
            'full_name' => $student->full_name,
            'registered_name' => $student->registered_name_resolved,
            'rut' => $student->rut,
            'photo_url' => '/build/images/users/user-dummy-img.jpg',
            'age' => $this->ageAt($student, $reference),
            'course' => $enrollment?->snapshot_course_display_name,
            'academic_year' => $enrollment?->snapshot_year_name,
            'teacher_name' => $teacher?->full_name,
            'health_insurance' => $student->health_insurance,
            'healthcare_provider' => $student->healthcare_provider,
            'blood_type' => $student->blood_type,
            'food_allergies' => $this->meaningfulMedicalText($student->food_allergies),
            'contraindicated_medications' => $this->meaningfulMedicalText($student->contraindicated_medications),
            'fit_for_physical_education' => $student->fit_for_physical_education,
            'has_private_school_insurance' => $student->has_private_school_insurance,
            'health_observations' => $this->meaningfulMedicalText($student->health_observations),
            'emergency_contacts' => $this->emergencyContacts($student),
            'allergies' => $student->has_medication_allergies ? $student->medication_allergies_details : null,
            'chronic_illness' => $student->has_chronic_illness ? $student->chronic_illness_details : null,
            'physical_restrictions' => $student->has_physical_restrictions ? $student->physical_restrictions_details : null,
            'medical_alerts' => $this->medicalAlerts($student, $permanentMedications),
            'permanent_medications' => $permanentMedications->map(fn (InfirmaryMedicationAuthorization $authorization) => [
                'id' => $authorization->id,
                'medication_name' => $authorization->medication?->commercial_name ?: $authorization->medication?->name,
                'dose' => $authorization->dose,
                'dose_amount' => $authorization->dose_amount,
                'dose_unit' => $authorization->dose_unit,
                'administration_route' => $authorization->administration_route,
                'frequency' => $authorization->frequency,
                'schedule_text' => $authorization->schedule_text,
                'regimen_type' => $authorization->regimen_type,
                'end_date' => $authorization->end_date,
                'status' => $authorization->status,
            ])->values()->all(),
            'history_summary' => $this->historySummary($student),
            'recent_attentions' => InfirmaryAttention::query()
                ->where('student_profile_id', $student->id)
                ->latest('attended_at')
                ->limit(5)
                ->get(['id', 'attended_at', 'attention_category', 'status', 'consultation_reason'])
                ->map(fn (InfirmaryAttention $attention) => [
                    'id' => $attention->id,
                    'attended_at' => $attention->attended_at,
                    'attention_category' => $attention->attention_category,
                    'status' => $attention->status,
                    'consultation_reason' => $attention->consultation_reason,
                ])
                ->values()
                ->all(),
        ];
    }

    public function studentSearchQuery(string $search, ?int $courseSectionId = null): Builder
    {
        $activeYear = $this->activeAcademicYear();
        $tokens = $this->studentSearchTokens($search);
        $searchableColumns = [
            $this->normalizedSearchColumn('first_name'),
            $this->normalizedSearchColumn('last_name'),
            $this->normalizedSearchColumn('registered_name'),
        ];
        $normalizedRutColumn = $this->normalizedRutColumn();

        return StudentProfile::query()
            ->with([
                'enrollments' => fn ($query) => $query
                    ->when($activeYear, fn ($inner) => $inner->where('academic_year_id', $activeYear->id))
                    ->with('courseSection:id,display_name'),
            ])
            ->when($tokens !== [], function (Builder $query) use ($tokens, $searchableColumns, $normalizedRutColumn) {
                foreach ($tokens as $token) {
                    $like = "%{$token}%";

                    $query->where(function (Builder $inner) use ($like, $searchableColumns, $normalizedRutColumn) {
                        foreach ($searchableColumns as $index => $column) {
                            $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                            $inner->{$method}("{$column} LIKE ?", [$like]);
                        }

                        $inner->orWhereRaw("{$normalizedRutColumn} LIKE ?", [$like]);
                    });
                }
            })
            ->when($search !== '' && $tokens === [], function (Builder $query) {
                $query->whereRaw('1 = 0');
            })
            ->when($courseSectionId, function (Builder $query) use ($courseSectionId, $activeYear) {
                $query->whereHas('enrollments', function (Builder $inner) use ($courseSectionId, $activeYear) {
                    if ($activeYear) {
                        $inner->where('academic_year_id', $activeYear->id);
                    }

                    $inner->where('course_section_id', $courseSectionId);
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchPayload(string $search, ?int $courseSectionId = null, int $limit = 12): array
    {
        return $this->studentSearchQuery($search, $courseSectionId)
            ->limit(max($limit * 10, 120))
            ->get()
            ->sort(function (StudentProfile $left, StudentProfile $right) use ($search) {
                $scoreComparison = $this->studentSearchScore($right, $search)
                    <=> $this->studentSearchScore($left, $search);

                if ($scoreComparison !== 0) {
                    return $scoreComparison;
                }

                return strnatcasecmp(
                    "{$left->last_name} {$left->first_name}",
                    "{$right->last_name} {$right->first_name}",
                );
            })
            ->take($limit)
            ->map(function (StudentProfile $student) {
                $summary = $this->studentSummary($student);

                return [
                    'id' => $student->id,
                    'full_name' => $student->full_name,
                    'rut' => $student->rut,
                    'course' => $summary['course'],
                    'teacher_name' => $summary['teacher_name'],
                    'age' => $summary['age'],
                    'photo_url' => $summary['photo_url'],
                    'guardian_name' => $student->guardian_name,
                    'guardian_relationship' => $student->guardian_relationship ?: $student->guardian_role,
                    'guardian_phone' => $student->guardian_phone,
                    'alerts' => array_values(array_filter([
                        $summary['allergies'] ? 'Alergias registradas' : null,
                        $summary['chronic_illness'] ? 'Enfermedad crónica' : null,
                        count($summary['permanent_medications']) > 0 ? 'Medicamentos permanentes' : null,
                        $summary['food_allergies'] ? 'Alergias alimentarias' : null,
                        $summary['contraindicated_medications'] ? 'Medicamentos contraindicados' : null,
                        $student->has_physical_restrictions ? 'Restricción física' : null,
                        $student->fit_for_physical_education === false ? 'No apta para Educación Física' : null,
                    ])),
                    'medical_context' => $summary,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function studentSearchTokens(string $search): array
    {
        $normalized = $this->normalizeStudentSearchText($search);

        if ($normalized === '') {
            return [];
        }

        return collect(explode(' ', $normalized))
            ->filter()
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    private function studentSearchScore(StudentProfile $student, string $search): int
    {
        $query = $this->normalizeStudentSearchText($search);
        $tokens = $this->studentSearchTokens($search);
        $fullName = $this->normalizeStudentSearchText($student->full_name);
        $registeredName = $this->normalizeStudentSearchText((string) $student->registered_name);
        $nameWords = collect(explode(' ', trim("{$fullName} {$registeredName}")))
            ->filter()
            ->unique()
            ->values();
        $normalizedRut = preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii((string) $student->rut))) ?: '';
        $compactQuery = preg_replace('/[^a-z0-9]+/', '', $query) ?: '';
        $score = 0;

        if ($query !== '' && $fullName === $query) {
            $score += 1200;
        } elseif ($query !== '' && str_starts_with($fullName, $query)) {
            $score += 700;
        }

        if ($query !== '' && $registeredName === $query) {
            $score += 1100;
        } elseif ($query !== '' && str_starts_with($registeredName, $query)) {
            $score += 650;
        }

        if ($compactQuery !== '' && $normalizedRut === $compactQuery) {
            $score += 1300;
        } elseif ($compactQuery !== '' && str_contains($normalizedRut, $compactQuery)) {
            $score += 500;
        }

        foreach ($tokens as $token) {
            if ($nameWords->contains($token)) {
                $score += 120;
            } elseif ($nameWords->contains(fn (string $word) => str_starts_with($word, $token))) {
                $score += 85;
            } elseif (str_contains("{$fullName} {$registeredName}", $token)) {
                $score += 45;
            }
        }

        return $score;
    }

    private function normalizeStudentSearchText(string $value): string
    {
        $normalized = Str::lower(Str::ascii(trim($value)));
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?: '';

        return trim(preg_replace('/\s+/', ' ', $normalized) ?: '');
    }

    private function normalizedSearchColumn(string $column): string
    {
        $expression = "COALESCE({$column}, '')";
        $replacements = [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ü' => 'U',
            'Ñ' => 'N',
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ];

        foreach ($replacements as $from => $to) {
            $expression = "REPLACE({$expression}, '{$from}', '{$to}')";
        }

        return "LOWER({$expression})";
    }

    private function normalizedRutColumn(): string
    {
        return "LOWER(REPLACE(REPLACE(REPLACE(COALESCE(rut, ''), '.', ''), '-', ''), ' ', ''))";
    }

    private function medicalAlerts(StudentProfile $student, Collection $permanentMedications): array
    {
        return collect([
            $student->has_medication_allergies
                ? ['level' => 'critical', 'label' => 'Alergia a medicamentos', 'detail' => $student->medication_allergies_details]
                : null,
            $this->meaningfulMedicalText($student->contraindicated_medications)
                ? ['level' => 'critical', 'label' => 'Medicamentos contraindicados', 'detail' => $this->meaningfulMedicalText($student->contraindicated_medications)]
                : null,
            $this->meaningfulMedicalText($student->food_allergies)
                ? ['level' => 'warning', 'label' => 'Alergias alimentarias', 'detail' => $this->meaningfulMedicalText($student->food_allergies)]
                : null,
            $student->has_chronic_illness
                ? ['level' => 'warning', 'label' => 'Enfermedad crónica', 'detail' => $student->chronic_illness_details]
                : null,
            $student->has_physical_restrictions
                ? ['level' => 'warning', 'label' => 'Restricción física', 'detail' => $student->physical_restrictions_details]
                : null,
            $student->fit_for_physical_education === false
                ? ['level' => 'warning', 'label' => 'No apta para Educación Física', 'detail' => null]
                : null,
            $permanentMedications->isNotEmpty()
                ? ['level' => 'info', 'label' => 'Medicación vigente', 'detail' => $permanentMedications
                    ->map(fn (InfirmaryMedicationAuthorization $authorization) => $authorization->medication?->commercial_name ?: $authorization->medication?->name)
                    ->filter()
                    ->join(', ')]
                : null,
        ])->filter()->values()->all();
    }

    private function meaningfulMedicalText(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $normalized = mb_strtoupper($value);
        if (in_array($normalized, ['NO', 'NINGUNA', 'NINGUNO', 'NO TIENE', 'NO PRESENTA', 'N/A', 'NA', '-'], true)) {
            return null;
        }

        return $value;
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function emergencyContacts(StudentProfile $student): array
    {
        return collect([
            [
                'type' => 'primary',
                'label' => 'Apoderado principal',
                'name' => $student->guardian_name,
                'relationship' => $student->guardian_relationship ?: $student->guardian_role,
                'phone' => $student->guardian_phone,
                'email' => $student->guardian_email,
            ],
            [
                'type' => 'backup',
                'label' => 'Apoderado suplente',
                'name' => $student->guardian_backup_name,
                'relationship' => $student->guardian_backup_relationship ?: $student->guardian_backup_role,
                'phone' => $student->guardian_backup_phone,
                'email' => $student->guardian_backup_email,
            ],
        ])->filter(fn (array $contact) => !empty($contact['name']))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function historySummary(StudentProfile $student): array
    {
        return [
            'attentions_total' => InfirmaryAttention::query()->where('student_profile_id', $student->id)->count(),
            'accidents_total' => InfirmaryAccident::query()->where('student_profile_id', $student->id)->count(),
            'administrations_total' => InfirmaryMedicationAdministration::query()->where('student_profile_id', $student->id)->count(),
            'authorizations_total' => InfirmaryMedicationAuthorization::query()->where('student_profile_id', $student->id)->count(),
            'last_attention_at' => InfirmaryAttention::query()
                ->where('student_profile_id', $student->id)
                ->max('attended_at'),
        ];
    }

    private function teachers(): Collection
    {
        if ($this->teachers instanceof Collection) {
            return $this->teachers;
        }

        return $this->teachers = Staff::query()
            ->with('cargo:id,slug')
            ->where('active', true)
            ->whereHas('cargo', fn ($query) => $query->whereIn('slug', ['docente', 'coordinador_academico']))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'cargo_id']);
    }
}
