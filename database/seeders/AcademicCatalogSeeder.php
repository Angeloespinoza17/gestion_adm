<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\Role;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Students\StudentEnrollmentLifecycleService;
use Database\Seeders\Support\PreventsProductionSeeding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AcademicCatalogSeeder extends Seeder
{
    use PreventsProductionSeeding;

    public function run(): void
    {
        $this->preventProductionSeeding();
        $this->call(EducationLevelSeeder::class);
        $levels = EducationLevel::query()->orderBy('order')->get()->keyBy('name');
        $years = $this->seedAcademicYears();
        $this->seedCourseSections($years, $levels);
        $this->seedDemoStudents($years);
    }

    private function seedAcademicYears()
    {
        $years = [
            ['name' => '2026', 'year' => 2026, 'starts_at' => '2026-03-01', 'ends_at' => '2026-12-31', 'is_active' => true, 'is_closed' => false],
            ['name' => '2027', 'year' => 2027, 'starts_at' => '2027-03-01', 'ends_at' => '2027-12-31', 'is_active' => false, 'is_closed' => false],
            ['name' => '2028', 'year' => 2028, 'starts_at' => '2028-03-01', 'ends_at' => '2028-12-31', 'is_active' => false, 'is_closed' => false],
        ];

        foreach ($years as $year) {
            AcademicYear::query()->updateOrCreate(
                ['year' => $year['year']],
                $year,
            );
        }

        return AcademicYear::query()->ordered()->get()->keyBy('year');
    }

    private function seedCourseSections($years, $levels): void
    {
        foreach ($years as $academicYear) {
            foreach ($levels as $level) {
                foreach (['A', 'B'] as $sectionName) {
                    CourseSection::query()->updateOrCreate(
                        [
                            'academic_year_id' => $academicYear->id,
                            'education_level_id' => $level->id,
                            'section_name' => $sectionName,
                        ],
                        [
                            'display_name' => CourseSection::makeDisplayName($level, $sectionName),
                            'capacity' => 35,
                            'active' => true,
                        ],
                    );
                }
            }
        }
    }

    private function seedDemoStudents($years): void
    {
        $lifecycleService = app(StudentEnrollmentLifecycleService::class);
        $studentRole = Role::query()->firstWhere('slug', 'estudiante');

        $students = [
            [
                'profile' => [
                    'first_name' => 'María',
                    'last_name' => 'Pérez Soto',
                    'rut' => '15555555-1',
                    'birthdate' => '2013-05-12',
                    'email' => 'maria.perez@example.com',
                    'phone' => '+56911111111',
                    'address' => 'Pasaje Los Alerces 120',
                    'general_status' => 'activo',
                    'guardian_name' => 'Ana Soto',
                    'guardian_relationship' => 'Madre',
                    'guardian_phone' => '+56920000001',
                    'guardian_email' => 'ana.soto@example.com',
                    'observations' => 'Estudiante de demostración con historial anual.',
                ],
                'password' => 'Estudiante123!',
                'history' => [
                    ['year' => 2026, 'level' => '7° básico', 'section' => 'A', 'status' => 'matriculada', 'date' => '2026-03-01'],
                    ['year' => 2027, 'level' => '8° básico', 'section' => 'A', 'status' => 'matriculada', 'date' => '2027-03-01'],
                    ['year' => 2028, 'level' => '1° medio', 'section' => 'B', 'status' => 'matriculada', 'date' => '2028-03-01'],
                ],
            ],
            [
                'profile' => [
                    'first_name' => 'Josefa',
                    'last_name' => 'Carrasco Díaz',
                    'rut' => '16666666-2',
                    'birthdate' => '2017-09-03',
                    'email' => 'josefa.carrasco@example.com',
                    'phone' => '+56911111112',
                    'address' => 'Villa Escolar 45',
                    'general_status' => 'activo',
                    'guardian_name' => 'Pablo Carrasco',
                    'guardian_relationship' => 'Padre',
                    'guardian_phone' => '+56920000002',
                    'guardian_email' => 'pablo.carrasco@example.com',
                ],
                'password' => 'Estudiante123!',
                'history' => [
                    ['year' => 2026, 'level' => 'NT1', 'section' => 'B', 'status' => 'matriculada', 'date' => '2026-03-01'],
                    ['year' => 2027, 'level' => 'NT2', 'section' => 'A', 'status' => 'matriculada', 'date' => '2027-03-01'],
                ],
            ],
            [
                'profile' => [
                    'first_name' => 'Catalina',
                    'last_name' => 'Rojas Fuentes',
                    'rut' => '17777777-3',
                    'birthdate' => '2009-01-28',
                    'email' => 'catalina.rojas@example.com',
                    'phone' => '+56911111113',
                    'address' => 'Camino al Río 890',
                    'general_status' => 'activo',
                    'guardian_name' => 'Marcela Fuentes',
                    'guardian_relationship' => 'Madre',
                    'guardian_phone' => '+56920000003',
                    'guardian_email' => 'marcela.fuentes@example.com',
                ],
                'password' => 'Estudiante123!',
                'history' => [
                    ['year' => 2026, 'level' => '4° medio', 'section' => 'A', 'status' => 'egresada', 'date' => '2026-03-01'],
                ],
            ],
        ];

        foreach ($students as $studentData) {
            $student = StudentProfile::query()->updateOrCreate(
                ['rut' => $studentData['profile']['rut']],
                $studentData['profile'],
            );

            $user = User::query()->updateOrCreate(
                ['student_id' => $student->id],
                [
                    'name' => $student->full_name,
                    'email' => $student->email,
                    'password' => Hash::make($studentData['password']),
                    'user_type' => 'student',
                    'active' => true,
                ],
            );

            if ($studentRole) {
                $user->roles()->syncWithoutDetaching([$studentRole->id]);
            }

            foreach ($studentData['history'] as $historyItem) {
                $academicYear = $years[$historyItem['year']];
                $courseSection = CourseSection::query()
                    ->where('academic_year_id', $academicYear->id)
                    ->whereHas('educationLevel', fn ($query) => $query->where('name', $historyItem['level']))
                    ->where('section_name', $historyItem['section'])
                    ->with('educationLevel')
                    ->firstOrFail();

                StudentEnrollment::query()->updateOrCreate(
                    [
                        'student_profile_id' => $student->id,
                        'academic_year_id' => $academicYear->id,
                    ],
                    array_merge([
                        'course_section_id' => $courseSection->id,
                        'enrollment_status' => $historyItem['status'],
                        'enrolled_at' => $historyItem['date'],
                        'withdrawn_at' => $historyItem['status'] === 'retirada' ? $historyItem['date'] : null,
                    ], StudentEnrollment::snapshotPayload($academicYear, $courseSection)),
                );

                $enrollment = StudentEnrollment::query()
                    ->where('student_profile_id', $student->id)
                    ->where('academic_year_id', $academicYear->id)
                    ->first();

                if ($enrollment) {
                    $lifecycleService->ensureInitialMovement($enrollment, null, 'Matrícula base del seeder académico.');
                }
            }
        }
    }
}
