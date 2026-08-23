<?php

namespace Tests\Feature\Grades;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\FeatureFlag;
use App\Models\LibroDigital\RegulatoryProfile;
use App\Models\LibroDigital\School;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Schedule\ScheduleSubject;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class GradeStatisticsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_aggregate_grade_statistics_with_pending_coverage_and_no_nominal_data(): void
    {
        Storage::fake('grade-statistics-tests');
        config()->set('grades.imports_disk', 'grade-statistics-tests');
        [$user, $school, $year, $secondStudent] = $this->context();

        $this->actingAs($user)->post('/api/students/grades/annual-imports', [
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'file' => UploadedFile::fake()->createWithContent('notas-estadisticas-2035.xlsx', $this->workbookBytes()),
        ])->assertCreated();

        $workspace = $this->getJson('/api/students/grades/annual-imports?school_id='.$school->id.'&academic_year_id='.$year->id)
            ->assertOk();
        $rowId = (int) $workspace->json('unmatched.0.id');
        $this->patchJson('/api/students/grades/annual-import-rows/'.$rowId.'/match?school_id='.$school->id, [
            'student_profile_id' => $secondStudent->id,
            'note' => 'Identidad comprobada para estadística de integración.',
        ])->assertOk();

        $response = $this->getJson('/api/students/grades/statistics?school_id='.$school->id.'&academic_year_id='.$year->id)
            ->assertOk()
            ->assertJsonPath('summary.assessments', 3)
            ->assertJsonPath('summary.students_evaluated', 2)
            ->assertJsonPath('summary.expected_results', 5)
            ->assertJsonPath('summary.completed_results', 3)
            ->assertJsonPath('summary.pending_results', 2)
            ->assertJsonPath('summary.comparable_results', 3)
            ->assertJsonPath('summary.average_grade', 5.5)
            ->assertJsonPath('summary.approval_rate', 100)
            ->assertJsonPath('summary.coverage_rate', 60)
            ->assertJsonPath('summary.imported_results', 3)
            ->assertJsonPath('summary.manual_results', 0)
            ->assertJsonPath('distribution.0.count', 0)
            ->assertJsonPath('distribution.1.count', 1)
            ->assertJsonPath('distribution.2.count', 1)
            ->assertJsonPath('distribution.3.count', 1)
            ->assertJsonPath('evaluation_progress.0.label', 'Evaluación 1')
            ->assertJsonPath('evaluation_progress.0.assessments', 1)
            ->assertJsonPath('evaluation_progress.0.results', 2)
            ->assertJsonPath('evaluation_progress.0.average_grade', 5.5)
            ->assertJsonPath('evaluation_progress.1.label', 'Evaluación 2')
            ->assertJsonPath('evaluation_progress.2.label', 'Evaluación 3')
            ->assertJsonPath('by_course.0.name', '5° Básico A')
            ->assertJsonPath('by_course.0.pending_results', 2)
            ->assertJsonPath('by_subject.0.name', 'Lenguaje')
            ->assertJsonPath('catalogs.academic_years.0.id', $year->id)
            ->assertJsonPath('catalogs.courses.0.name', '5° Básico A')
            ->assertJsonPath('catalogs.subjects.0.name', 'Lenguaje')
            ->assertJsonCount(1, 'catalogs.periods');

        $payload = $response->getContent();
        $this->assertStringNotContainsString('Primera Alumna', $payload);
        $this->assertStringNotContainsString('Segunda Alumna', $payload);
        $this->assertStringNotContainsString('11.111.111-1', $payload);

        $this->getJson('/api/students/grades/statistics/students?school_id='.$school->id.'&academic_year_id='.$year->id)
            ->assertOk()
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonPath('data.0.name', 'Primera Alumna')
            ->assertJsonPath('data.0.courses.0.name', '5° Básico A')
            ->assertJsonPath('data.0.assessments', 3)
            ->assertJsonPath('data.0.completed_results', 2)
            ->assertJsonPath('data.0.pending_results', 1)
            ->assertJsonPath('data.0.average_grade', 5.85)
            ->assertJsonPath('data.1.name', 'Segunda Alumna')
            ->assertJsonMissingPath('data.0.rut');

        $firstStudentId = StudentProfile::query()->where('registered_name', 'Primera Alumna')->value('id');
        $this->getJson('/api/students/grades/statistics/students/'.$firstStudentId.'?school_id='.$school->id.'&academic_year_id='.$year->id)
            ->assertOk()
            ->assertJsonPath('student.name', 'Primera Alumna')
            ->assertJsonPath('student.courses.0.name', '5° Básico A')
            ->assertJsonPath('summary.assessments', 3)
            ->assertJsonPath('summary.expected_results', 3)
            ->assertJsonPath('summary.completed_results', 2)
            ->assertJsonPath('summary.pending_results', 1)
            ->assertJsonPath('summary.average_grade', 5.85)
            ->assertJsonPath('by_subject.0.name', 'Lenguaje')
            ->assertJsonPath('evaluations.0.label', 'Evaluación 1')
            ->assertJsonPath('evaluations.0.state', 'recorded')
            ->assertJsonPath('evaluations.0.grade', 6.2)
            ->assertJsonPath('evaluations.1.state', 'missing')
            ->assertJsonPath('evaluations.1.state_label', 'Sin registrar')
            ->assertJsonPath('evaluations.2.grade', 5.5)
            ->assertJsonMissingPath('student.rut');
    }

    public function test_it_rejects_users_without_the_statistics_permission(): void
    {
        [, $school, $year] = $this->context();
        $user = User::factory()->create(['active' => true]);

        $this->actingAs($user)
            ->getJson('/api/students/grades/statistics?school_id='.$school->id.'&academic_year_id='.$year->id)
            ->assertForbidden();
    }

    public function test_nominal_consolidation_requires_its_explicit_permission(): void
    {
        [, $school, $year] = $this->context();
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->create(['slug' => 'analista_agregado', 'name' => 'Analista agregado', 'active' => true]);
        $role->permissions()->attach(Permission::query()->where('slug', 'grade_statistics.view')->firstOrFail());
        $user->roles()->attach($role);
        $school->users()->attach($user, [
            'role_snapshot' => $role->slug,
            'permission_scope' => 'school',
            'active' => true,
        ]);

        $this->actingAs($user)
            ->getJson('/api/students/grades/statistics?school_id='.$school->id.'&academic_year_id='.$year->id)
            ->assertOk();
        $this->getJson('/api/students/grades/statistics/students?school_id='.$school->id.'&academic_year_id='.$year->id)
            ->assertForbidden();
        $this->getJson('/api/students/grades/statistics/students/1?school_id='.$school->id.'&academic_year_id='.$year->id)
            ->assertForbidden();
    }

    /** @return array{User,School,AcademicYear,StudentProfile} */
    private function context(): array
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin', 'active' => true]);
        $user->roles()->syncWithoutDetaching([$role->id]);
        $school = School::query()->create(['rbd' => '22345-6', 'name' => 'Escuela Analítica', 'timezone' => 'America/Santiago', 'active' => true]);
        $year = AcademicYear::factory()->create(['year' => 2035, 'name' => '2035', 'starts_at' => '2035-03-01', 'ends_at' => '2035-12-20', 'is_active' => true]);
        $level = EducationLevel::factory()->create(['name' => 'Quinto básico analítico', 'order' => 506, 'type' => 'basica']);
        $course = CourseSection::factory()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'display_name' => '5° Básico A',
            'section_name' => 'A',
        ]);
        $subject = ScheduleSubject::query()->create(['name' => 'Lenguaje', 'code' => 'LEN-STATS', 'area' => 'Lenguaje', 'color' => '#405189', 'active' => true]);
        $teacher = Staff::query()->create(['full_name' => 'Docente Estadísticas', 'rut' => '12.345.678-5', 'active' => true]);
        $regulatoryProfile = RegulatoryProfile::query()->create([
            'code' => 'CL-LCD-STATS',
            'name' => 'Perfil normativo estadísticas',
            'version' => '1.0',
            'effective_from' => '2030-01-01',
            'retention_years' => 6,
            'rules_snapshot' => [],
            'active' => true,
        ]);
        $school->academicYears()->attach($year->id, [
            'regulatory_profile_id' => $regulatoryProfile->id,
            'rbd_snapshot' => $school->rbd,
            'year_snapshot' => $year->year,
            'timezone_snapshot' => $school->timezone,
            'active' => true,
        ]);
        FeatureFlag::query()->create(['school_id' => $school->id, 'scope_key' => 'school:'.$school->id, 'code' => 'lcd_enabled', 'enabled' => true]);

        $firstStudent = StudentProfile::factory()->create(['registered_name' => 'Primera Alumna', 'rut' => '11.111.111-1']);
        $secondStudent = StudentProfile::factory()->create(['registered_name' => 'Segunda Alumna', 'rut' => '22.222.222-2']);
        foreach ([$firstStudent, $secondStudent] as $student) {
            StudentEnrollment::query()->create([
                'student_profile_id' => $student->id,
                'academic_year_id' => $year->id,
                'course_section_id' => $course->id,
                'enrollment_status' => 'matriculada',
                'enrolled_at' => $year->starts_at,
                'snapshot_year_name' => $year->name,
                'snapshot_level_name' => $level->name,
                'snapshot_section_name' => $course->section_name,
                'snapshot_course_display_name' => $course->display_name,
            ]);
        }

        $this->actingAs($user)->withHeader('Idempotency-Key', 'grade-statistics-book-context')
            ->postJson('/api/libro-digital/v1/books', [
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'course_section_id' => $course->id,
                'schedule_subject_id' => $subject->id,
                'teacher_staff_id' => $teacher->id,
                'normative_profile_id' => $regulatoryProfile->id,
                'name' => 'Libro de Lenguaje',
                'modality' => 'regular',
            ])->assertCreated();
        Book::query()->firstOrFail()->forceFill(['status' => 'open', 'opened_at' => now('UTC')])->save();

        return [$user, $school, $year, $secondStudent];
    }

    private function workbookBytes(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('5° Básico A');
        $sheet->fromArray([
            ['Establecimiento:', 'Escuela Analítica'],
            ['Curso:', '5º Básico A'],
            ['Rango de fecha:', '01/03/2035 ➡ 20/12/2035'],
            [],
            ['', '', '', 'Lenguaje', '', ''],
            ['N° de lista', 'Estudiantes', 'RUN', 'N1', 'N2', 'N3'],
            [1, 'Primera Alumna', '11.111.111-1', 6.2, 'P', 5.5],
            [2, 'Alumna por conciliar', '99.999.999-9', 4.8, '-', 'P'],
        ], null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'grade-statistics-test-');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        $bytes = file_get_contents($path);
        @unlink($path);

        return (string) $bytes;
    }
}
