<?php

namespace Tests\Feature\Attendance;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Attendance\MonthlyAttendanceWorkbookParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Tests\TestCase;

class MonthlyAttendanceExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_parser_reads_the_monthly_lirmi_workbook_structure(): void
    {
        $upload = $this->workbook([
            ['course' => '1º Básico A', 'run' => '11111111', 'dv' => '1', 'paternal' => 'Alpha', 'maternal' => 'Uno', 'names' => 'Ana', 'day_1' => '●', 'day_2' => 'X', 'present' => 1, 'absent' => 1, 'classes' => 2, 'priority' => 'Si', 'preferential' => 'No', 'pie' => 'Si'],
        ]);

        $parsed = app(MonthlyAttendanceWorkbookParser::class)->parse($upload->getRealPath());

        $this->assertSame(2026, $parsed['year']);
        $this->assertSame(3, $parsed['month']);
        $this->assertSame(1, $parsed['summary']['students']);
        $this->assertSame(50.0, $parsed['summary']['attendance_rate']);
        $this->assertTrue($parsed['rows'][0]['is_sep_priority']);
        $this->assertTrue($parsed['rows'][0]['is_pie']);
        $this->assertSame(['present', 'absent'], array_column($parsed['rows'][0]['daily_records'], 'status'));
    }

    public function test_authorized_user_imports_all_courses_and_resolves_unmatched_students_later(): void
    {
        [$year, $course, $students] = $this->academicContext();
        Storage::fake('attendance-test');
        config()->set('attendance.imports_disk', 'attendance-test');
        $user = $this->authorizedUser();
        Sanctum::actingAs($user);

        $response = $this->post('/api/students/attendance/monthly-imports', [
            'academic_year_id' => $year->id,
            'file' => $this->workbook([
                ['course' => '1º Básico A', 'run' => '11111111', 'dv' => '1', 'paternal' => 'Alpha', 'maternal' => 'Uno', 'names' => 'Ana', 'day_1' => '●', 'day_2' => 'X', 'present' => 1, 'absent' => 1, 'classes' => 2, 'priority' => 'Si', 'preferential' => 'No', 'pie' => 'Si'],
                ['course' => '1º Básico A', 'run' => '99999999', 'dv' => '9', 'paternal' => 'Beta', 'maternal' => 'Dos', 'names' => 'Beatriz', 'day_1' => '●', 'day_2' => '●', 'present' => 2, 'absent' => 0, 'classes' => 2, 'priority' => 'No', 'preferential' => 'Si', 'pie' => 'No'],
            ]),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'partial')
            ->assertJsonPath('data.matched_rows', 1)
            ->assertJsonPath('data.unmatched_rows', 1)
            ->assertJsonPath('data.imported_records', 2);
        $this->assertDatabaseCount('monthly_attendance_imports', 1);
        $this->assertDatabaseCount('monthly_attendance_import_rows', 2);
        $this->assertDatabaseCount('attendance_records', 2);
        $this->assertDatabaseHas('student_profiles', ['id' => $students[0]->id, 'is_pie_participant' => true]);
        $this->assertDatabaseHas('pme_estudiantes_sep', [
            'student_profile_id' => $students[0]->id,
            'academic_year_id' => $year->id,
            'classification' => 'prioritaria',
        ]);

        $workspace = $this->getJson('/api/students/attendance/monthly-imports?academic_year_id='.$year->id)
            ->assertOk()
            ->assertJsonPath('summary.unmatched', 1)
            ->assertJsonCount(1, 'unmatched');
        $rowId = $workspace->json('unmatched.0.id');
        $candidateIds = collect($workspace->json('unmatched.0.candidates'))->pluck('student_profile_id');
        $this->assertFalse($candidateIds->contains($students[0]->id));
        $this->assertTrue($candidateIds->contains($students[1]->id));

        $this->getJson('/api/students/attendance/monthly-imports/candidates?monthly_attendance_import_row_id='.$rowId.'&search=Ana')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->getJson('/api/students/attendance/monthly-imports/candidates?monthly_attendance_import_row_id='.$rowId.'&search=Beatriz')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student_profile_id', $students[1]->id);

        $this->patchJson('/api/students/attendance/monthly-import-rows/'.$rowId.'/match', [
            'student_profile_id' => $students[1]->id,
            'note' => 'RUT de origen corregido contra matrícula anual.',
        ])->assertOk()
            ->assertJsonPath('data.matched_student.id', $students[1]->id)
            ->assertJsonPath('data.match_status', 'manual');
        $this->patchJson('/api/students/attendance/monthly-import-rows/'.$rowId.'/match', [
            'student_profile_id' => $students[0]->id,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('attendance_records', 4);
        $this->assertDatabaseHas('monthly_attendance_imports', [
            'academic_year_id' => $year->id,
            'month' => 3,
            'status' => 'completed',
            'matched_rows' => 2,
            'unmatched_rows' => 0,
        ]);
        $this->assertDatabaseHas('pme_estudiantes_sep', [
            'student_profile_id' => $students[1]->id,
            'classification' => 'preferente',
        ]);

        $this->getJson('/api/students/'.$students[1]->id)
            ->assertOk()
            ->assertJsonPath('data.attendance_profile.latest.period', '2026-03')
            ->assertJsonPath('data.attendance_profile.latest.is_sep_preferential', true)
            ->assertJsonPath('data.attendance_profile.latest.attendance_rate', 100);
    }

    public function test_imported_support_flags_do_not_overwrite_authoritative_manual_records(): void
    {
        [$year, , $students] = $this->academicContext();
        Storage::fake('attendance-test');
        config()->set('attendance.imports_disk', 'attendance-test');
        Sanctum::actingAs($this->authorizedUser());
        $students[0]->update(['is_pie_participant' => true]);
        DB::table('pme_estudiantes_sep')->insert([
            'student_profile_id' => $students[0]->id,
            'academic_year_id' => $year->id,
            'classification' => 'prioritaria',
            'source' => 'Validación manual UTP',
            'state' => 'vigente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('/api/students/attendance/monthly-imports', [
            'academic_year_id' => $year->id,
            'file' => $this->workbook([
                ['course' => '1º Básico A', 'run' => '11111111', 'dv' => '1', 'paternal' => 'Alpha', 'maternal' => 'Uno', 'names' => 'Ana', 'day_1' => '●', 'day_2' => '●', 'present' => 2, 'absent' => 0, 'classes' => 2, 'priority' => 'No', 'preferential' => 'Si', 'pie' => 'No'],
            ]),
        ])->assertCreated();

        $this->assertDatabaseHas('student_profiles', [
            'id' => $students[0]->id,
            'is_pie_participant' => true,
        ]);
        $this->assertDatabaseHas('pme_estudiantes_sep', [
            'student_profile_id' => $students[0]->id,
            'classification' => 'prioritaria',
            'source' => 'Validación manual UTP',
        ]);
    }

    public function test_reuploading_a_month_preserves_the_previous_version_without_deleting_it(): void
    {
        [$year] = $this->academicContext();
        Storage::fake('attendance-test');
        config()->set('attendance.imports_disk', 'attendance-test');
        Sanctum::actingAs($this->authorizedUser());

        $first = $this->workbook([
            ['course' => '1º Básico A', 'run' => '11111111', 'dv' => '1', 'paternal' => 'Alpha', 'maternal' => 'Uno', 'names' => 'Ana', 'day_1' => '●', 'day_2' => 'X', 'present' => 1, 'absent' => 1, 'classes' => 2, 'priority' => 'No', 'preferential' => 'No', 'pie' => 'No'],
        ], 'marzo-v1.xls');
        $second = $this->workbook([
            ['course' => '1º Básico A', 'run' => '11111111', 'dv' => '1', 'paternal' => 'Alpha', 'maternal' => 'Uno', 'names' => 'Ana', 'day_1' => '●', 'day_2' => '●', 'present' => 2, 'absent' => 0, 'classes' => 2, 'priority' => 'No', 'preferential' => 'No', 'pie' => 'No'],
        ], 'marzo-v2.xls');

        $this->post('/api/students/attendance/monthly-imports', ['academic_year_id' => $year->id, 'file' => $first])->assertCreated();
        $this->post('/api/students/attendance/monthly-imports', ['academic_year_id' => $year->id, 'file' => $second])->assertCreated();

        $this->assertDatabaseCount('monthly_attendance_imports', 2);
        $this->assertDatabaseHas('monthly_attendance_imports', ['version' => 1, 'status' => 'superseded', 'is_active' => false]);
        $this->assertDatabaseHas('monthly_attendance_imports', ['version' => 2, 'status' => 'completed', 'is_active' => true]);
        $this->assertDatabaseHas('attendance_records', ['attendance_date' => '2026-03-02', 'status' => 'present']);
    }

    /** @return array{0:AcademicYear,1:CourseSection,2:array<int,StudentProfile>} */
    private function academicContext(): array
    {
        $year = AcademicYear::query()->create([
            'name' => '2026', 'year' => 2026, 'starts_at' => '2026-03-01',
            'ends_at' => '2026-12-31', 'is_active' => true, 'is_closed' => false,
        ]);
        $level = EducationLevel::query()->create(['name' => 'Nivel importación mensual', 'order' => 991, 'type' => 'basica']);
        $course = CourseSection::query()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'section_name' => 'A',
            'display_name' => '1º Básico A',
            'active' => true,
        ]);
        $students = collect([
            ['first_name' => 'Ana', 'last_name' => 'Alpha Uno', 'registered_name' => 'Alpha Uno Ana', 'rut' => '11.111.111-1'],
            ['first_name' => 'Beatriz', 'last_name' => 'Beta Dos', 'registered_name' => 'Beta Dos Beatriz', 'rut' => '22.222.222-2'],
        ])->map(function (array $payload) use ($year, $course): StudentProfile {
            $student = StudentProfile::query()->create([...$payload, 'general_status' => 'activo']);
            StudentEnrollment::query()->create([
                'student_profile_id' => $student->id,
                'academic_year_id' => $year->id,
                'course_section_id' => $course->id,
                'enrollment_status' => 'regular',
                'snapshot_year_name' => '2026',
                'snapshot_level_name' => '1º Básico',
                'snapshot_section_name' => 'A',
                'snapshot_course_display_name' => '1º Básico A',
            ]);

            return $student;
        })->all();

        return [$year, $course, $students];
    }

    private function authorizedUser(): User
    {
        $user = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $role = Role::query()->create(['name' => 'Importadora asistencia', 'slug' => 'importadora-asistencia', 'active' => true]);
        $permissions = collect([
            ['name' => 'Importar asistencia', 'slug' => 'importar_asistencia', 'active' => true],
            ['name' => 'Ver ficha estudiante', 'slug' => 'ver_ficha_estudiante', 'active' => true],
        ])->map(fn (array $attributes): Permission => Permission::query()->create($attributes));
        $role->permissions()->attach($permissions->pluck('id'));
        $user->roles()->attach($role);

        return $user;
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function workbook(array $rows, string $filename = 'marzo.xls'): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('1º Básico');
        $sheet->setCellValue('A1', 'Marzo 2026');
        foreach ([
            'A2' => 'Curso', 'B2' => 'Nº Lista', 'C2' => 'Apellido Paterno', 'D2' => 'Apellido Materno',
            'E2' => 'Nombres', 'F2' => 'Run', 'G2' => 'DV', 'I2' => 1, 'J2' => 2,
            'AO2' => 'Asist', 'AP2' => 'Inasist', 'AQ2' => 'Días de clases', 'AS2' => 'Es prioritario',
            'AT2' => 'Es Preferente', 'AU2' => 'Es PIE',
        ] as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
        foreach ($rows as $index => $row) {
            $number = $index + 3;
            foreach ([
                'A' => $row['course'], 'B' => $index + 1, 'C' => $row['paternal'], 'D' => $row['maternal'],
                'E' => $row['names'], 'F' => $row['run'], 'G' => $row['dv'], 'I' => $row['day_1'],
                'J' => $row['day_2'], 'AO' => $row['present'], 'AP' => $row['absent'], 'AQ' => $row['classes'],
                'AS' => $row['priority'], 'AT' => $row['preferential'], 'AU' => $row['pie'],
            ] as $column => $value) {
                $sheet->setCellValue($column.$number, $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'attendance-xls-');
        (new Xls($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile($path, $filename, 'application/vnd.ms-excel', null, true);
    }
}
