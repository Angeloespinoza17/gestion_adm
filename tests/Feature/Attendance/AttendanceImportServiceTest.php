<?php

namespace Tests\Feature\Attendance;

use App\Models\AcademicYear;
use App\Models\Attendance\AttendanceProjectionSetting;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Services\Attendance\AttendanceAlertService;
use App\Services\Attendance\AttendanceImportService;
use App\Services\Attendance\AttendanceParserRegistry;
use App\Services\Attendance\AttendanceProjectionService;
use App\Services\Attendance\AttendanceStatisticsCache;
use App\Services\Attendance\AttendanceStudentMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class AttendanceImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_confirmation_and_duplicate_confirmation_are_idempotent(): void
    {
        [$year, $course, $students] = $this->academicContext();
        Storage::fake('attendance-test');
        config()->set('attendance.imports_disk', 'attendance-test');
        $parsed = $this->parsedPayload();
        $parsers = Mockery::mock(AttendanceParserRegistry::class);
        $parsers->shouldReceive('parse')->once()->andReturn($parsed);
        $service = new AttendanceImportService(
            $parsers,
            app(AttendanceStudentMatcher::class),
            app(AttendanceAlertService::class),
            app(AttendanceStatisticsCache::class),
        );

        $preview = $service->preview(
            UploadedFile::fake()->createWithContent('abril.pdf', '%PDF-anonymized-attendance'),
            $course,
            null,
        );

        $this->assertSame('preview', $preview->status);
        $this->assertSame(2, $preview->matched_students);
        $this->assertSame(0, $preview->unmatched_students);

        $confirmed = $service->confirm($preview, ['conflict_strategy' => 'reject'], null);
        $duplicate = $service->preview(
            UploadedFile::fake()->createWithContent('abril-segunda-copia.pdf', '%PDF-anonymized-attendance'),
            $course,
            null,
        );
        $confirmedAgain = $service->confirm($duplicate, ['conflict_strategy' => 'overwrite'], null);

        $this->assertSame('completed', $confirmed->status);
        $this->assertSame($confirmed->id, $duplicate->id);
        $this->assertSame($confirmed->id, $confirmedAgain->id);
        $this->assertDatabaseCount('attendance_imports', 1);
        $this->assertDatabaseCount('attendance_records', 4);
        $this->assertDatabaseHas('school_days', ['date' => '2026-04-02', 'status' => 'pending_confirmation']);
        $this->assertDatabaseHas('attendance_records', [
            'student_profile_id' => $students[0]->id,
            'attendance_date' => '2026-04-01',
            'status' => 'present',
        ]);
    }

    public function test_parser_failures_are_returned_as_file_validation_errors(): void
    {
        [, $course] = $this->academicContext();
        Storage::fake('attendance-test');
        config()->set('attendance.imports_disk', 'attendance-test');
        $parsers = Mockery::mock(AttendanceParserRegistry::class);
        $parsers->shouldReceive('parse')->once()->andThrow(new \RuntimeException('Formato de cabecera no compatible.'));
        $service = new AttendanceImportService(
            $parsers,
            app(AttendanceStudentMatcher::class),
            app(AttendanceAlertService::class),
            app(AttendanceStatisticsCache::class),
        );

        try {
            $service->preview(
                UploadedFile::fake()->createWithContent('invalido.pdf', '%PDF-invalid-attendance'),
                $course,
                null,
            );
            $this->fail('La previsualización debía rechazar el archivo.');
        } catch (ValidationException $exception) {
            $this->assertSame(['Formato de cabecera no compatible.'], $exception->errors()['file']);
        }

        $this->assertSame([], Storage::disk('attendance-test')->allFiles());
    }

    public function test_student_matching_includes_withdrawn_students_from_the_selected_course(): void
    {
        [$year, $course] = $this->academicContext();
        $student = StudentProfile::query()->create([
            'first_name' => 'Josefa Amparo',
            'last_name' => 'Martinez Quiroga',
            'registered_name' => 'Josefa Amparo Martinez Quiroga',
            'rut' => '33.333.333-3',
            'general_status' => 'retirado',
        ]);
        $enrollment = StudentEnrollment::query()->create([
            'student_profile_id' => $student->id,
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'enrollment_status' => 'retirada',
            'snapshot_year_name' => '2026',
            'snapshot_level_name' => '2º Básico',
            'snapshot_section_name' => 'A',
            'snapshot_course_display_name' => '2º Básico A',
        ]);

        $matched = app(AttendanceStudentMatcher::class)->match([
            ['row' => 1, 'name' => 'Martinez Quiroga Josefa Amparo'],
        ], $course);

        $this->assertSame('exact', $matched[0]['match_status']);
        $this->assertSame($student->id, $matched[0]['matched_student_id']);
        $this->assertSame($enrollment->id, $matched[0]['matched_enrollment_id']);
    }

    public function test_projection_values_come_from_persisted_settings(): void
    {
        [$year] = $this->academicContext();
        AttendanceProjectionSetting::query()->create([
            'academic_year_id' => $year->id,
            'monthly_unit_value' => 1000,
            'attendance_factor' => 0.5,
            'annual_school_days' => 190,
            'currency' => 'CLP',
        ]);

        $projection = app(AttendanceProjectionService::class)->build($year->id, null, 90, 20);

        $this->assertSame(170, $projection['remaining_school_days']);
        $this->assertSame(90.0, $projection['scenarios'][0]['attendance_rate']);
        $this->assertSame(1.8, $projection['scenarios'][0]['average_daily_attendance']);
        $this->assertSame(900.0, $projection['scenarios'][0]['monthly_revenue']);
        $this->assertSame(['trend', 'conservative', 'target', 'custom'], array_column($projection['scenarios'], 'key'));
        $this->assertFalse($projection['configuration_required']);
        $this->assertTrue($projection['is_estimate']);
    }

    public function test_projection_reports_missing_financial_configuration_without_inventing_values(): void
    {
        [$year] = $this->academicContext();

        $projection = app(AttendanceProjectionService::class)->build($year->id, null, 89.68, 21, 452, 504);

        $this->assertTrue($projection['configuration_required']);
        $this->assertSame(4, count($projection['scenarios']));
        $this->assertSame(85.0, $projection['scenarios'][2]['scenario_rate']);
    }

    private function academicContext(): array
    {
        $year = AcademicYear::query()->create([
            'name' => '2026', 'year' => 2026, 'starts_at' => '2026-03-01',
            'ends_at' => '2026-12-31', 'is_active' => true, 'is_closed' => false,
        ]);
        $level = EducationLevel::query()->create(['name' => '2º Básico', 'order' => 2, 'type' => 'basica']);
        $course = CourseSection::query()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'section_name' => 'A',
            'display_name' => '2º Básico A',
            'active' => true,
        ]);
        $students = collect([
            ['first_name' => 'Ana', 'last_name' => 'Alpha', 'registered_name' => 'Alpha Ana', 'rut' => '11.111.111-1'],
            ['first_name' => 'Bea', 'last_name' => 'Beta', 'registered_name' => 'Beta Bea', 'rut' => '22.222.222-2'],
        ])->map(function (array $payload) use ($year, $course) {
            $student = StudentProfile::query()->create([...$payload, 'general_status' => 'activo']);
            StudentEnrollment::query()->create([
                'student_profile_id' => $student->id,
                'academic_year_id' => $year->id,
                'course_section_id' => $course->id,
                'enrollment_status' => 'regular',
                'snapshot_year_name' => '2026',
                'snapshot_level_name' => '2º Básico',
                'snapshot_section_name' => 'A',
                'snapshot_course_display_name' => '2º Básico A',
            ]);

            return $student;
        })->all();

        return [$year, $course, $students];
    }

    private function parsedPayload(): array
    {
        return [
            'document' => [
                'source' => 'lirmi_pdf', 'course_name' => '2º Básico A', 'year' => 2026,
                'month' => 4, 'month_label' => 'Abril', 'period' => '2026-04',
            ],
            'summary' => [
                'students' => 2, 'school_days' => 2, 'present' => 2, 'absent' => 2,
                'possible' => 4, 'attendance_rate' => 50.0, 'average_daily_attendance' => 1.0,
                'students_below_85' => 2, 'anomaly_days' => 1,
            ],
            'days' => [
                ['day' => 1, 'date' => '2026-04-01', 'present' => 2, 'absent' => 0, 'enrolled' => 2, 'attendance_rate' => 100.0, 'is_anomaly' => false, 'confirmation_status' => 'confirmed'],
                ['day' => 2, 'date' => '2026-04-02', 'present' => 0, 'absent' => 2, 'enrolled' => 2, 'attendance_rate' => 0.0, 'is_anomaly' => true, 'confirmation_status' => 'pending_confirmation'],
            ],
            'students' => [
                ['row' => 1, 'name' => 'Alpha Ana', 'present' => 1, 'absent' => 1, 'total' => 2, 'attendance_rate' => 50.0, 'records' => [
                    ['day' => 1, 'date' => '2026-04-01', 'status' => 'present', 'symbol' => '●'],
                    ['day' => 2, 'date' => '2026-04-02', 'status' => 'absent', 'symbol' => 'X'],
                ]],
                ['row' => 2, 'name' => 'Beta Bea', 'present' => 1, 'absent' => 1, 'total' => 2, 'attendance_rate' => 50.0, 'records' => [
                    ['day' => 1, 'date' => '2026-04-01', 'status' => 'present', 'symbol' => '●'],
                    ['day' => 2, 'date' => '2026-04-02', 'status' => 'absent', 'symbol' => 'X'],
                ]],
            ],
            'validation' => [
                ['code' => 'present_totals', 'passed' => true, 'level' => 'success'],
                ['code' => 'absent_totals', 'passed' => true, 'level' => 'success'],
                ['code' => 'possible_totals', 'passed' => true, 'level' => 'success'],
            ],
        ];
    }
}
