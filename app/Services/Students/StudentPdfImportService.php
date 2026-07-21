<?php

namespace App\Services\Students;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\Rut;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StudentPdfImportService
{
    private const EDUCATION_LEVEL_CATALOG = [
        'nt1' => ['name' => 'NT1', 'order' => 1, 'type' => 'parvularia'],
        'nt2' => ['name' => 'NT2', 'order' => 2, 'type' => 'parvularia'],
        '1 basico' => ['name' => '1° básico', 'order' => 3, 'type' => 'basica'],
        '2 basico' => ['name' => '2° básico', 'order' => 4, 'type' => 'basica'],
        '3 basico' => ['name' => '3° básico', 'order' => 5, 'type' => 'basica'],
        '4 basico' => ['name' => '4° básico', 'order' => 6, 'type' => 'basica'],
        '5 basico' => ['name' => '5° básico', 'order' => 7, 'type' => 'basica'],
        '6 basico' => ['name' => '6° básico', 'order' => 8, 'type' => 'basica'],
        '7 basico' => ['name' => '7° básico', 'order' => 9, 'type' => 'basica'],
        '8 basico' => ['name' => '8° básico', 'order' => 10, 'type' => 'basica'],
        '1 medio' => ['name' => '1° medio', 'order' => 11, 'type' => 'media'],
        '2 medio' => ['name' => '2° medio', 'order' => 12, 'type' => 'media'],
        '3 medio' => ['name' => '3° medio', 'order' => 13, 'type' => 'media'],
        '4 medio' => ['name' => '4° medio', 'order' => 14, 'type' => 'media'],
    ];

    public function __construct(
        private readonly LirmiStudentPdfParser $parser,
        private readonly StudentAccountService $accountService,
        private readonly StudentEnrollmentLifecycleService $enrollmentLifecycleService,
    ) {}

    public function import(UploadedFile $file, ?User $actor = null, ?int $courseSectionId = null): array
    {
        return $this->importPath($file->getRealPath(), $actor, $courseSectionId);
    }

    public function importPath(string $path, ?User $actor = null, ?int $courseSectionId = null): array
    {
        $records = $this->parser->parseFile($path);
        $targetCourseSection = $courseSectionId
            ? CourseSection::query()->with(['academicYear', 'educationLevel'])->findOrFail($courseSectionId)
            : null;
        $result = [
            'total' => count($records),
            'created' => 0,
            'updated' => 0,
            'enrollments' => 0,
            'errors' => [],
            'warnings' => [],
        ];

        foreach ($records as $record) {
            try {
                $operation = DB::transaction(function () use ($record, $actor, $targetCourseSection, &$result) {
                    return $this->importRecord($record, $actor, $targetCourseSection, $result['warnings']);
                });
                $result[$operation['created'] ? 'created' : 'updated']++;
                $result['enrollments'] += $operation['enrollment_saved'] ? 1 : 0;
            } catch (Throwable $exception) {
                report($exception);
                $result['errors'][] = [
                    'page' => $record['page'] ?? null,
                    'student' => $record['profile']['registered_name'] ?? 'Estudiante sin nombre',
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return $result;
    }

    private function importRecord(
        array $record,
        ?User $actor,
        ?CourseSection $targetCourseSection,
        array &$warnings,
    ): array {
        $profilePayload = $this->withoutEmptyValues($record['profile'] ?? []);
        $registeredName = trim((string) ($profilePayload['registered_name'] ?? ''));

        if ($registeredName === '' || empty($profilePayload['first_name']) || empty($profilePayload['last_name'])) {
            throw new RuntimeException('No fue posible identificar el nombre completo de la estudiante.');
        }

        $rut = $profilePayload['rut'] ?? null;
        if ($rut && ! Rut::isValid($rut)) {
            throw new RuntimeException("El RUN {$rut} no es válido.");
        }

        $profilePayload['general_status'] = $this->generalStatus($record['enrollment']['source_status'] ?? null);
        $this->appendParentData($profilePayload);

        $student = $this->findStudent($profilePayload);
        $created = ! $student;
        $student = $student
            ? $this->accountService->update($student, $profilePayload, [], $actor)
            : $this->accountService->store($profilePayload, ['account_active' => true], $actor);

        $enrollmentSaved = $this->saveEnrollment(
            $student,
            $record['enrollment'] ?? [],
            $actor,
            $targetCourseSection,
            $record['page'] ?? null,
            $warnings,
        );

        return [
            'created' => $created,
            'enrollment_saved' => $enrollmentSaved,
        ];
    }

    private function findStudent(array $payload): ?StudentProfile
    {
        if (! empty($payload['rut'])) {
            return StudentProfile::query()->firstWhere('rut', $payload['rut']);
        }

        return StudentProfile::query()
            ->where('registered_name', $payload['registered_name'])
            ->when(
                ! empty($payload['birthdate']),
                fn ($query) => $query->whereDate('birthdate', $payload['birthdate']),
            )
            ->first();
    }

    private function saveEnrollment(
        StudentProfile $student,
        array $payload,
        ?User $actor,
        ?CourseSection $targetCourseSection,
        ?int $page,
        array &$warnings,
    ): bool {
        $yearNumber = (int) ($payload['year'] ?? 0);
        $courseName = trim((string) ($payload['course_name'] ?? ''));

        if (! $targetCourseSection && (! $yearNumber || $courseName === '')) {
            $warnings[] = $this->warning($page, $student, 'No se pudo identificar el año o curso de matrícula.');

            return false;
        }

        $academicYear = $targetCourseSection?->academicYear ?? $this->resolveAcademicYear($yearNumber, $actor);
        $courseSection = $targetCourseSection ?? $this->resolveCourseSection($academicYear, $courseName, $actor);

        if (! $courseSection) {
            $warnings[] = $this->warning($page, $student, "No se encontró un nivel equivalente para el curso {$courseName}.");

            return false;
        }

        $enrollment = StudentEnrollment::query()->firstOrNew([
            'student_profile_id' => $student->id,
            'academic_year_id' => $academicYear->id,
        ]);
        $isNew = ! $enrollment->exists;
        $enrollment->fill(array_merge([
            'course_section_id' => $courseSection->id,
            'enrollment_status' => $this->enrollmentStatus($payload['source_status'] ?? null),
            'registration_number' => $payload['registration_number'] ?? null,
            'enrolled_at' => $payload['enrolled_at'] ?? null,
            'updated_by' => $actor?->id,
        ], StudentEnrollment::snapshotPayload($academicYear, $courseSection)));

        if ($isNew) {
            $enrollment->created_by = $actor?->id;
        }

        $enrollment->save();
        $this->enrollmentLifecycleService->ensureInitialMovement(
            $enrollment,
            $actor,
            'Matrícula importada desde ficha PDF Lirmi.',
        );

        return true;
    }

    private function resolveAcademicYear(int $year, ?User $actor): AcademicYear
    {
        $existing = AcademicYear::query()->firstWhere('year', $year);
        if ($existing) {
            return $existing;
        }

        return AcademicYear::query()->create([
            'name' => (string) $year,
            'year' => $year,
            'starts_at' => "{$year}-03-01",
            'ends_at' => "{$year}-12-31",
            'is_active' => ! AcademicYear::query()->where('is_active', true)->exists(),
            'is_closed' => false,
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);
    }

    private function resolveCourseSection(AcademicYear $year, string $courseName, ?User $actor): ?CourseSection
    {
        $normalizedCourse = $this->normalize($courseName);
        $sectionName = 'A';

        if (preg_match('/\s+([a-z]|\d{1,2})$/', $normalizedCourse, $matches)) {
            $sectionName = strtoupper($matches[1]);
            $normalizedCourse = trim(substr($normalizedCourse, 0, -strlen($matches[0])));
        }

        $canonicalLevel = $this->canonicalLevel($normalizedCourse);
        $existingCourse = CourseSection::query()
            ->with('educationLevel')
            ->where('academic_year_id', $year->id)
            ->get()
            ->first(
                fn (CourseSection $course) => $this->normalize($course->section_name) === $this->normalize($sectionName)
                    && ($this->normalize($course->display_name) === $this->normalize($courseName)
                        || $this->canonicalLevel((string) $course->educationLevel?->name) === $canonicalLevel),
            );

        if ($existingCourse) {
            return $existingCourse;
        }

        $level = EducationLevel::query()->get()->first(
            fn (EducationLevel $level) => $this->canonicalLevel($level->name) === $canonicalLevel,
        );

        $level ??= $this->createKnownEducationLevel($canonicalLevel);

        if (! $level) {
            return null;
        }

        return CourseSection::query()->firstOrCreate([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'section_name' => $sectionName,
        ], [
            'display_name' => CourseSection::makeDisplayName($level, $sectionName),
            'active' => true,
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);
    }

    private function createKnownEducationLevel(string $canonicalLevel): ?EducationLevel
    {
        $definition = self::EDUCATION_LEVEL_CATALOG[$canonicalLevel] ?? null;
        if (! $definition) {
            return null;
        }

        $existing = EducationLevel::query()->firstWhere('name', $definition['name']);
        if ($existing) {
            return $existing;
        }

        $order = $definition['order'];
        if (EducationLevel::query()->where('order', $order)->exists()) {
            $order = ((int) EducationLevel::query()->max('order')) + 1;
        }

        return EducationLevel::query()->create([
            'name' => $definition['name'],
            'order' => $order,
            'type' => $definition['type'],
        ]);
    }

    private function canonicalLevel(string $level): string
    {
        $level = $this->normalize($level);
        $aliases = [
            'primer nivel transicion' => 'nt1',
            'primer nivel de transicion' => 'nt1',
            'nivel transicion 1' => 'nt1',
            'nt 1' => 'nt1',
            'pre kinder' => 'nt1',
            'prekinder' => 'nt1',
            'segundo nivel transicion' => 'nt2',
            'segundo nivel de transicion' => 'nt2',
            'nivel transicion 2' => 'nt2',
            'nt 2' => 'nt2',
            'kinder' => 'nt2',
        ];

        if (isset($aliases[$level])) {
            return $aliases[$level];
        }

        $numbers = [
            'primero' => 1,
            'primer' => 1,
            'segundo' => 2,
            'tercero' => 3,
            'cuarto' => 4,
            'quinto' => 5,
            'sexto' => 6,
            'septimo' => 7,
            'octavo' => 8,
        ];

        if (! preg_match('/^(\d+|'.implode('|', array_keys($numbers)).')\s+(basico|medio)$/', $level, $matches)) {
            return $level;
        }

        $number = ctype_digit($matches[1]) ? (int) $matches[1] : $numbers[$matches[1]];

        return "{$number} {$matches[2]}";
    }

    private function appendParentData(array &$payload): void
    {
        foreach (['guardian', 'guardian_backup'] as $prefix) {
            $relationship = $this->normalize((string) ($payload["{$prefix}_relationship"] ?? ''));
            $target = str_contains($relationship, 'padre')
                ? 'father'
                : (str_contains($relationship, 'madre') ? 'mother' : null);

            if (! $target || ! empty($payload["{$target}_name"])) {
                continue;
            }

            foreach ([
                'name' => 'name',
                'rut' => 'rut',
                'address' => 'address',
                'email' => 'email',
                'occupation' => 'occupation',
                'phone' => 'phone',
                'education_level' => 'education_level',
            ] as $source => $destination) {
                if (array_key_exists("{$prefix}_{$source}", $payload)) {
                    $payload["{$target}_{$destination}"] = $payload["{$prefix}_{$source}"];
                }
            }
        }
    }

    private function withoutEmptyValues(array $payload): array
    {
        return array_filter($payload, static fn ($value) => $value !== null && $value !== '');
    }

    private function enrollmentStatus(?string $status): string
    {
        return match ($this->normalize((string) $status)) {
            'retirado', 'retirada' => 'retirada',
            'egresado', 'egresada' => 'egresada',
            'suspendido', 'suspendida' => 'suspendida',
            'trasladado', 'trasladada' => 'trasladada',
            'regular' => 'regular',
            default => 'matriculada',
        };
    }

    private function generalStatus(?string $status): string
    {
        return match ($this->enrollmentStatus($status)) {
            'retirada', 'trasladada' => 'retirado',
            'egresada' => 'egresado',
            'suspendida' => 'suspendido',
            default => 'activo',
        };
    }

    private function normalize(string $value): string
    {
        $value = Str::lower(Str::ascii(trim($value)));

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $value));
    }

    private function warning(?int $page, StudentProfile $student, string $message): array
    {
        return [
            'page' => $page,
            'student' => $student->registered_name_resolved,
            'message' => $message,
        ];
    }
}
