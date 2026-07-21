<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Services\Students\StudentAccountService;
use App\Services\Students\StudentEnrollmentLifecycleService;
use Carbon\Carbon;
use Database\Seeders\Support\PreventsProductionSeeding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class StudentTestingSeeder extends Seeder
{
    use PreventsProductionSeeding;

    public function run(): void
    {
        $this->preventProductionSeeding();
        $this->call([
            RbacSeeder::class,
            EducationLevelSeeder::class,
        ]);

        $years = $this->ensureAcademicYears();
        $levels = EducationLevel::query()->orderBy('order')->get();
        $this->ensureCourseSections($years, $levels);

        $accountService = app(StudentAccountService::class);
        $lifecycleService = app(StudentEnrollmentLifecycleService::class);
        $yearsAsc = AcademicYear::query()->orderBy('year')->get()->values();
        $courses = CourseSection::query()
            ->with('educationLevel')
            ->get()
            ->groupBy(fn (CourseSection $course) => sprintf('%d-%d', $course->academic_year_id, $course->education_level_id));

        $firstNames = ['Martina', 'Sofía', 'Isidora', 'Emilia', 'Florencia', 'Agustina', 'Josefa', 'Catalina', 'Antonia', 'Fernanda', 'Amanda', 'Renata', 'Maite', 'Violeta', 'Julieta'];
        $lastNamesA = ['Pérez', 'González', 'Muñoz', 'Rojas', 'Díaz', 'Silva', 'Morales', 'Torres', 'Contreras', 'Sepúlveda', 'Araya', 'Castillo'];
        $lastNamesB = ['Soto', 'Fuentes', 'Vargas', 'Carrasco', 'Navarro', 'Hernández', 'Ramírez', 'Molina', 'Saavedra', 'Jara', 'Cortés', 'Espinoza'];

        foreach (range(1, 90) as $index) {
            $firstName = $firstNames[($index - 1) % count($firstNames)];
            $lastName = $lastNamesA[($index - 1) % count($lastNamesA)] . ' ' . $lastNamesB[($index + 2) % count($lastNamesB)];
            $rutNumber = 21000000 + $index;
            $rut = sprintf('%d-%d', $rutNumber, (($index % 9) + 1));
            $email = sprintf('estudiante.prueba%03d@cnscgestion.local', $index);
            $birthYear = 2008 + ($index % 10);
            $birthMonth = (($index % 12) + 1);
            $birthDay = (($index % 27) + 1);

            $student = StudentProfile::query()->firstWhere('rut', $rut);
            $profilePayload = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'rut' => $rut,
                'birthdate' => Carbon::create($birthYear, $birthMonth, $birthDay)->format('Y-m-d'),
                'email' => $email,
                'phone' => '+5699' . str_pad((string) (1000000 + $index), 7, '0', STR_PAD_LEFT),
                'address' => sprintf('Pasaje Escolar %d', 100 + $index),
                'general_status' => 'activo',
                'guardian_name' => sprintf('Apoderado %03d', $index),
                'guardian_relationship' => $index % 2 === 0 ? 'Madre' : 'Padre',
                'guardian_phone' => '+5698' . str_pad((string) (1000000 + $index), 7, '0', STR_PAD_LEFT),
                'guardian_email' => sprintf('apoderado%03d@cnscgestion.local', $index),
                'observations' => 'Registro de prueba generado automáticamente para validar cursos, retiros y trazabilidad anual.',
            ];

            if ($student) {
                $student = $accountService->update($student, $profilePayload, ['account_active' => true], null);
            } else {
                $student = $accountService->store($profilePayload, [
                    'account_active' => true,
                    'password' => 'Estudiante123!',
                ], null);
            }

            $historyStartIndex = $index % max($yearsAsc->count(), 1);
            $historyLength = min(($index % 3) + 1, $yearsAsc->count() - $historyStartIndex);
            $startingLevelOrder = min(($index % 10) + 1, max($levels->count() - $historyLength + 1, 1));

            foreach (range(0, max($historyLength - 1, 0)) as $offset) {
                $year = $yearsAsc[$historyStartIndex + $offset];
                $level = $levels->firstWhere('order', min($startingLevelOrder + $offset, $levels->max('order')));
                $bucket = $courses->get(sprintf('%d-%d', $year->id, $level->id), collect());

                if ($bucket->isEmpty()) {
                    continue;
                }

                $course = $bucket[($index + $offset) % $bucket->count()];
                $status = $year->is_active
                    ? 'regular'
                    : ($level->name === '4° medio' && $offset === $historyLength - 1 ? 'egresada' : 'matriculada');
                $enrolledAt = $year->starts_at?->copy()->addDays(($index + $offset) % 25)?->format('Y-m-d') ?: now()->format('Y-m-d');

                $enrollment = StudentEnrollment::query()->updateOrCreate(
                    [
                        'student_profile_id' => $student->id,
                        'academic_year_id' => $year->id,
                    ],
                    array_merge([
                        'course_section_id' => $course->id,
                        'enrollment_status' => $status,
                        'enrolled_at' => $enrolledAt,
                        'withdrawn_at' => null,
                    ], StudentEnrollment::snapshotPayload($year, $course)),
                );

                $lifecycleService->ensureInitialMovement($enrollment, null, 'Matrícula de prueba generada automáticamente.');
            }

            $activeEnrollment = StudentEnrollment::query()
                ->with(['academicYear', 'courseSection.educationLevel', 'movements', 'studentProfile'])
                ->where('student_profile_id', $student->id)
                ->whereHas('academicYear', fn ($query) => $query->where('is_active', true))
                ->first();

            if (!$activeEnrollment || $activeEnrollment->movements()->count() > 1) {
                continue;
            }

            $alternateCourses = CourseSection::query()
                ->with('educationLevel')
                ->where('academic_year_id', $activeEnrollment->academic_year_id)
                ->where('education_level_id', $activeEnrollment->courseSection->education_level_id)
                ->where('id', '!=', $activeEnrollment->course_section_id)
                ->orderBy('section_name')
                ->get();

            if ($index % 15 === 0 && $alternateCourses->isNotEmpty()) {
                $destinationCourse = $alternateCourses->first();
                $lifecycleService->withdraw($activeEnrollment, [
                    'effective_date' => Carbon::parse($activeEnrollment->enrolled_at)->addDays(35)->format('Y-m-d'),
                    'notes' => 'Retiro temporal de prueba.',
                ], null);

                $lifecycleService->reenter($activeEnrollment->fresh(['academicYear', 'courseSection.educationLevel', 'studentProfile']), $destinationCourse, [
                    'effective_date' => Carbon::parse($activeEnrollment->enrolled_at)->addDays(60)->format('Y-m-d'),
                    'enrollment_status' => 'regular',
                    'notes' => 'Reingreso de prueba en otro paralelo.',
                ], null);
            } elseif ($index % 9 === 0) {
                $lifecycleService->withdraw($activeEnrollment, [
                    'effective_date' => Carbon::parse($activeEnrollment->enrolled_at)->addDays(45)->format('Y-m-d'),
                    'notes' => 'Retiro de prueba para validar listado de retiradas.',
                ], null);
            } elseif ($index % 5 === 0 && $alternateCourses->isNotEmpty()) {
                $lifecycleService->transfer($activeEnrollment, $alternateCourses->first(), [
                    'effective_date' => Carbon::parse($activeEnrollment->enrolled_at)->addDays(25)->format('Y-m-d'),
                    'notes' => 'Cambio de curso de prueba.',
                ], null);
            }
        }

        $this->ensureRetiredExamples($lifecycleService);
    }

    private function ensureAcademicYears(): Collection
    {
        $currentYear = (int) now()->format('Y');
        $existingActiveYearId = AcademicYear::query()->where('is_active', true)->value('id');
        $yearDefinitions = [
            ['year' => $currentYear - 1, 'active' => false],
            ['year' => $currentYear, 'active' => true],
            ['year' => $currentYear + 1, 'active' => false],
        ];

        foreach ($yearDefinitions as $definition) {
            AcademicYear::query()->updateOrCreate(
                ['year' => $definition['year']],
                [
                    'name' => (string) $definition['year'],
                    'starts_at' => sprintf('%d-03-01', $definition['year']),
                    'ends_at' => sprintf('%d-12-31', $definition['year']),
                    'is_active' => false,
                    'is_closed' => false,
                ],
            );
        }

        $activeYearId = $existingActiveYearId
            ?: AcademicYear::query()->where('year', $currentYear)->value('id');

        if ($activeYearId) {
            AcademicYear::query()->update(['is_active' => false]);
            AcademicYear::query()->where('id', $activeYearId)->update(['is_active' => true]);
        }

        return AcademicYear::query()->orderBy('year')->get();
    }

    private function ensureCourseSections(Collection $years, Collection $levels): void
    {
        foreach ($years as $academicYear) {
            foreach ($levels as $level) {
                foreach (['A', 'B', 'C'] as $sectionName) {
                    CourseSection::query()->updateOrCreate(
                        [
                            'academic_year_id' => $academicYear->id,
                            'education_level_id' => $level->id,
                            'section_name' => $sectionName,
                        ],
                        [
                            'display_name' => CourseSection::makeDisplayName($level, $sectionName),
                            'capacity' => 32 + (($academicYear->year + $level->order + ord($sectionName)) % 10),
                            'active' => true,
                        ],
                    );
                }
            }
        }
    }

    private function ensureRetiredExamples(StudentEnrollmentLifecycleService $lifecycleService): void
    {
        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');

        if (!$activeYearId) {
            return;
        }

        $retiredCount = StudentEnrollment::query()
            ->where('academic_year_id', $activeYearId)
            ->where('enrollment_status', 'retirada')
            ->count();

        $missing = max(0, 5 - $retiredCount);
        if ($missing === 0) {
            return;
        }

        $candidates = StudentEnrollment::query()
            ->with(['academicYear', 'courseSection.educationLevel', 'studentProfile', 'movements'])
            ->where('academic_year_id', $activeYearId)
            ->whereNotIn('enrollment_status', ['retirada', 'egresada', 'trasladada'])
            ->orderBy('id')
            ->get();

        foreach ($candidates as $candidate) {
            if ($missing === 0) {
                break;
            }

            if ($candidate->movements()->count() > 1) {
                continue;
            }

            $lifecycleService->withdraw($candidate, [
                'effective_date' => Carbon::parse($candidate->enrolled_at)->addDays(50)->format('Y-m-d'),
                'notes' => 'Retiro adicional de prueba para validar listado anual.',
            ], null);

            $missing--;
        }
    }
}
