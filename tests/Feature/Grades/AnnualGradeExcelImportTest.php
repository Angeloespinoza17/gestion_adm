<?php

namespace Tests\Feature\Grades;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\Grades\AnnualGradeImport;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\FeatureFlag;
use App\Models\LibroDigital\RegulatoryProfile;
use App\Models\LibroDigital\School;
use App\Models\LibroDigital\StudentResult;
use App\Models\LibroDigital\SubjectExternalAlias;
use App\Models\Role;
use App\Models\Schedule\ScheduleSubject;
use App\Models\Staff;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\LibroDigital\BookProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AnnualGradeExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_variable_grades_allows_manual_match_and_never_duplicates_or_overwrites_manual_results(): void
    {
        Storage::fake('grade-tests');
        config()->set('grades.imports_disk', 'grade-tests');
        [$user, $school, $year, $student, $unmatchedTarget] = $this->context();
        $bytes = $this->workbookBytes(6.2);

        $created = $this->actingAs($user)->post('/api/students/grades/annual-imports', [
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'file' => UploadedFile::fake()->createWithContent('notas-2035.xlsx', $bytes),
        ]);

        $created->assertCreated()
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('data.courses', 1)
            ->assertJsonPath('data.student_rows', 2)
            ->assertJsonPath('data.columns', 3)
            ->assertJsonPath('data.cells', 6)
            ->assertJsonPath('data.numeric_cells', 3)
            ->assertJsonPath('data.pending_cells', 2)
            ->assertJsonPath('data.not_applicable_cells', 1)
            ->assertJsonPath('data.matched_rows', 1)
            ->assertJsonPath('data.unmatched_rows', 1)
            ->assertJsonPath('data.applied_results', 2);

        $import = AnnualGradeImport::query()->firstOrFail();
        $this->assertDatabaseCount('annual_grade_imports', 1);
        $this->assertDatabaseCount('lcd_assessments', 3);
        $this->assertDatabaseCount('lcd_student_results', 2);
        $this->assertDatabaseHas('lcd_student_results', [
            'student_profile_id' => $student->id,
            'numeric_value' => 6.2,
            'annual_grade_import_id' => $import->id,
        ]);
        $this->assertSame(2, DB::table('annual_grade_import_columns')->where('source_header', 'N2')->count());
        $this->assertSame([1, 2], DB::table('annual_grade_import_columns')->where('source_header', 'N2')->orderBy('id')->pluck('header_occurrence')->map(fn ($value) => (int) $value)->all());

        $workspace = $this->getJson('/api/students/grades/annual-imports?school_id='.$school->id.'&academic_year_id='.$year->id)
            ->assertOk()
            ->assertJsonPath('summary.unmatched', 1)
            ->assertJsonPath('unmatched.0.source_name', 'Alumna Sin Coincidencia');
        $rowId = (int) $workspace->json('unmatched.0.id');

        $this->getJson('/api/students/grades/annual-imports/candidates?school_id='.$school->id.'&annual_grade_import_row_id='.$rowId.'&search=Segunda')
            ->assertOk()
            ->assertJsonPath('data.0.student_profile_id', $unmatchedTarget->id);
        $this->patchJson('/api/students/grades/annual-import-rows/'.$rowId.'/match?school_id='.$school->id, [
            'student_profile_id' => $unmatchedTarget->id,
            'note' => 'Identidad verificada contra la matrícula oficial.',
        ])->assertOk()->assertJsonPath('data.match_status', 'manual');

        $this->assertDatabaseCount('lcd_student_results', 4);
        $this->assertDatabaseHas('lcd_student_results', [
            'student_profile_id' => $unmatchedTarget->id,
            'status' => 'exempt',
            'exempt' => true,
            'numeric_value' => null,
        ]);
        $this->assertSame(4, (int) $import->refresh()->applied_results);
        $this->assertSame(4, DB::table('lcd_record_revisions')->where('revisable_type', StudentResult::class)->count());

        $this->post('/api/students/grades/annual-imports', [
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'file' => UploadedFile::fake()->createWithContent('notas-2035.xlsx', $bytes),
        ])->assertOk()->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount('annual_grade_imports', 1);
        $this->assertDatabaseCount('lcd_assessments', 3);
        $this->assertDatabaseCount('lcd_student_results', 4);
        $this->assertSame(4, DB::table('lcd_record_revisions')->where('revisable_type', StudentResult::class)->count());

        $manualResult = StudentResult::query()
            ->where('student_profile_id', $student->id)
            ->where('numeric_value', 6.2)
            ->firstOrFail();
        $manualResult->forceFill([
            'numeric_value' => 6.8,
            'annual_grade_import_id' => null,
            'annual_grade_import_cell_id' => null,
        ])->save();

        $this->post('/api/students/grades/annual-imports', [
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'file' => UploadedFile::fake()->createWithContent('notas-2035-corregidas.xlsx', $this->workbookBytes(5.0)),
        ])->assertCreated()
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.matched_rows', 2)
            ->assertJsonPath('data.unmatched_rows', 0)
            ->assertJsonPath('data.preserved_manual_results', 1);

        $this->assertDatabaseCount('annual_grade_imports', 2);
        $this->assertDatabaseCount('lcd_assessments', 3);
        $this->assertDatabaseCount('lcd_student_results', 4);
        $this->assertSame(6.8, (float) $manualResult->refresh()->numeric_value);
        $this->assertNull($manualResult->annual_grade_import_id);
        $this->assertDatabaseHas('annual_grade_import_rows', [
            'annual_grade_import_id' => AnnualGradeImport::query()->where('version', 2)->value('id'),
            'student_profile_id' => $unmatchedTarget->id,
            'match_status' => 'carried_manual',
        ]);
        $this->assertDatabaseHas('annual_grade_import_cells', [
            'annual_grade_import_id' => AnnualGradeImport::query()->where('version', 2)->value('id'),
            'apply_status' => 'preserved_manual',
            'student_result_id' => $manualResult->id,
        ]);
    }

    public function test_it_associates_annual_grades_to_the_subject_when_the_book_has_no_teacher(): void
    {
        Storage::fake('grade-tests');
        config()->set('grades.imports_disk', 'grade-tests');
        [$user, $school, $year, $student] = $this->context(false);

        $this->actingAs($user)->post('/api/students/grades/annual-imports', [
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'file' => UploadedFile::fake()->createWithContent('notas-sin-docente-2035.xlsx', $this->workbookBytes(6.2)),
        ])->assertCreated()
            ->assertJsonPath('data.applied_results', 2);

        $book = Book::query()->firstOrFail();
        $this->assertDatabaseCount('lcd_assessments', 3);
        $this->assertDatabaseCount('lcd_student_results', 2);
        $this->assertDatabaseMissing('annual_grade_import_columns', [
            'mapping_status' => 'assignment_missing',
        ]);
        $this->assertSame(
            3,
            DB::table('annual_grade_import_columns')
                ->where('book_id', $book->id)
                ->whereNotNull('schedule_subject_id')
                ->whereNull('teacher_assignment_id')
                ->where('mapping_status', 'mapped')
                ->count(),
        );
        $this->assertSame(
            3,
            DB::table('lcd_assessments')
                ->where('book_id', $book->id)
                ->whereNotNull('schedule_subject_id')
                ->whereNull('teacher_assignment_id')
                ->count(),
        );
        $assessment = $book->assessments()->firstOrFail();
        $this->assertTrue((bool) $assessment->instrument_metadata['teacher_assignment_pending']);
        $this->assertSame('unassigned_at_import', $assessment->instrument_metadata['teacher_attribution']);
        $this->assertDatabaseHas('lcd_student_results', [
            'assessment_id' => $assessment->id,
            'student_profile_id' => $student->id,
            'annual_grade_import_id' => AnnualGradeImport::query()->value('id'),
        ]);
    }

    /** @return array{User,School,AcademicYear,StudentProfile,StudentProfile} */
    private function context(bool $withTeacher = true): array
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin', 'active' => true]);
        $user->roles()->syncWithoutDetaching([$role->id]);
        $school = School::query()->create(['rbd' => '12345-6', 'name' => 'Escuela Prueba', 'timezone' => 'America/Santiago', 'active' => true]);
        $year = AcademicYear::factory()->create(['year' => 2035, 'name' => '2035', 'starts_at' => '2035-03-01', 'ends_at' => '2035-12-20', 'is_active' => true]);
        $level = EducationLevel::factory()->create(['name' => 'Quinto básico de prueba', 'order' => 505, 'type' => 'basica']);
        $course = CourseSection::factory()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'display_name' => '5° Básico A',
            'section_name' => 'A',
        ]);
        $subject = ScheduleSubject::query()->create(['name' => 'Lenguaje', 'code' => 'LEN-05', 'area' => 'Lenguaje', 'color' => '#405189', 'active' => true]);
        SubjectExternalAlias::query()->create([
            'school_id' => $school->id,
            'schedule_subject_id' => $subject->id,
            'source_system' => 'legacy_gradebook',
            'scope_code' => 'basica',
            'education_type' => 'basica',
            'external_name' => 'Lenguaje y Comunicación',
            'normalized_name' => 'lenguaje y comunicacion',
            'mapping_key' => hash('sha256', 'basica|lenguaje y comunicacion'),
            'active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'confirmed_at' => now('UTC'),
        ]);
        $teacher = $withTeacher
            ? Staff::query()->create(['full_name' => 'Docente Prueba', 'rut' => '12.345.678-5', 'active' => true])
            : null;
        $profile = RegulatoryProfile::query()->create([
            'code' => 'CL-LCD',
            'name' => 'Perfil normativo',
            'version' => '1.0',
            'effective_from' => '2030-01-01',
            'retention_years' => 6,
            'rules_snapshot' => [],
            'active' => true,
        ]);
        $school->academicYears()->attach($year->id, [
            'regulatory_profile_id' => $profile->id,
            'rbd_snapshot' => $school->rbd,
            'year_snapshot' => $year->year,
            'timezone_snapshot' => $school->timezone,
            'active' => true,
        ]);
        FeatureFlag::query()->create(['school_id' => $school->id, 'scope_key' => 'school:'.$school->id, 'code' => 'lcd_enabled', 'enabled' => true]);

        $student = StudentProfile::factory()->create(['registered_name' => 'Primera Alumna', 'rut' => '11.111.111-1']);
        $unmatchedTarget = StudentProfile::factory()->create(['registered_name' => 'Segunda Alumna', 'rut' => '22.222.222-2']);
        foreach ([$student, $unmatchedTarget] as $profileStudent) {
            StudentEnrollment::query()->create([
                'student_profile_id' => $profileStudent->id,
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

        if ($teacher) {
            $this->actingAs($user)->withHeader('Idempotency-Key', 'grade-import-book-context')
                ->postJson('/api/libro-digital/v1/books', [
                    'school_id' => $school->id,
                    'academic_year_id' => $year->id,
                    'course_section_id' => $course->id,
                    'schedule_subject_id' => $subject->id,
                    'teacher_staff_id' => $teacher->id,
                    'normative_profile_id' => $profile->id,
                    'name' => 'Libro de Lenguaje',
                    'modality' => 'regular',
                ])->assertCreated();
        } else {
            app(BookProvisioningService::class)->provision(
                $school,
                $year,
                $course,
                $subject,
                $profile,
                $user,
                null,
                ['name' => 'Libro de Lenguaje'],
            );
        }
        Book::query()->firstOrFail()->forceFill(['status' => 'open', 'opened_at' => now('UTC')])->save();

        return [$user, $school, $year, $student, $unmatchedTarget];
    }

    private function workbookBytes(float $firstGrade): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('5° Básico A');
        $sheet->fromArray([
            ['Establecimiento:', 'Escuela Prueba'],
            ['Curso:', '5º Básico A'],
            ['Rango de fecha:', '01/03/2035 ➡ 20/12/2035'],
            [],
            ['', '', '', 'Lenguaje y Comunicación', '', ''],
            ['N° de lista', 'Estudiantes', 'RUN', 'N1', 'N2', 'N2'],
            [1, 'Primera Alumna', '11.111.111-1', $firstGrade, 'P', 5.5],
            [2, 'Alumna Sin Coincidencia', '99.999.999-9', 4.8, '-', 'P'],
        ], null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'grade-import-test-');
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
        $bytes = file_get_contents($path);
        @unlink($path);

        return (string) $bytes;
    }
}
