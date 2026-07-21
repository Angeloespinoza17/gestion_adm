<?php

namespace Tests\Feature\Attendance;

use App\Models\AcademicYear;
use App\Models\Attendance\AttendanceExportJob;
use App\Models\Attendance\AttendanceGoal;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Attendance\AttendanceScheduledReport;
use App\Models\Attendance\SchoolDay;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceStatisticsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_permission_and_returns_aggregated_drill_down_data(): void
    {
        [$year, $course, $student, $user] = $this->scenario();
        Sanctum::actingAs($user);

        $this->getJson('/api/attendance-statistics/dashboard?academic_year_id='.$year->id)
            ->assertForbidden();

        $this->grant($user, ['attendance_statistics.view']);

        $this->getJson('/api/attendance-statistics/dashboard?academic_year_id='.$year->id.'&period=academic_year')
            ->assertOk()
            ->assertJsonPath('summary.present', 3)
            ->assertJsonPath('summary.absent', 1)
            ->assertJsonPath('summary.attendance_rate', 75)
            ->assertJsonPath('courses.0.id', $course->id)
            ->assertJsonPath('courses.0.attendance_rate', 75)
            ->assertJsonPath('risk_distribution.0.value', 1)
            ->assertJsonStructure([
                'meta' => ['academic_year', 'date_range', 'capabilities', 'source'],
                'catalogs' => ['academic_years', 'levels', 'courses', 'risk_levels', 'absence_reasons'],
                'summary', 'kpis', 'timeline', 'monthly', 'weekdays', 'courses', 'levels',
                'risk_distribution', 'statistics', 'status_distribution', 'alert_funnel',
            ]);
    }

    public function test_student_explorer_and_profile_use_server_side_filters(): void
    {
        [$year, $course, $student, $user] = $this->scenario();
        $this->grant($user, ['attendance_statistics.view_student']);
        Sanctum::actingAs($user);

        $this->getJson('/api/attendance-statistics/students?academic_year_id='.$year->id.'&course_section_id='.$course->id.'&attendance_max=80')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $student->id)
            ->assertJsonPath('data.0.risk.slug', 'high');

        $this->getJson('/api/attendance-statistics/students/'.$student->id.'?academic_year_id='.$year->id)
            ->assertOk()
            ->assertJsonPath('student.course_id', $course->id)
            ->assertJsonPath('summary.maximum_consecutive_absences', 1)
            ->assertJsonCount(4, 'records');

        $this->assertDatabaseHas('attendance_statistics_audit_logs', ['action' => 'student_sensitive_view', 'auditable_id' => $student->id]);
    }

    public function test_goals_are_validated_authorized_and_audited(): void
    {
        [$year, , , $user] = $this->scenario();
        $this->grant($user, ['attendance_statistics.view', 'attendance_statistics.manage_goals']);
        Sanctum::actingAs($user);
        $payload = [
            'academic_year_id' => $year->id, 'name' => 'Meta institucional', 'scope_type' => 'institution',
            'starts_on' => '2026-03-01', 'ends_on' => '2026-12-20', 'target_rate' => 92,
            'status' => 'active', 'justification' => 'Plan anual', 'reason' => 'Aprobada por dirección.',
        ];

        $response = $this->postJson('/api/attendance-statistics/goals', $payload)
            ->assertCreated()
            ->assertJsonPath('data.target_rate', 92);
        $goal = AttendanceGoal::query()->findOrFail($response->json('data.id'));

        $this->putJson('/api/attendance-statistics/goals/'.$goal->id, [...$payload, 'target_rate' => 93, 'reason' => 'Ajuste de cierre.'])
            ->assertOk()
            ->assertJsonPath('data.target_rate', 93);

        $this->assertDatabaseHas('attendance_statistics_audit_logs', ['action' => 'goal_created', 'auditable_id' => $goal->id]);
        $this->assertDatabaseHas('attendance_statistics_audit_logs', ['action' => 'goal_updated', 'auditable_id' => $goal->id]);
    }

    public function test_simulation_and_async_pdf_export_complete_with_downloadable_file(): void
    {
        Storage::fake('local');
        Mail::fake();
        [$year, , , $user] = $this->scenario();
        $this->grant($user, ['attendance_statistics.view', 'attendance_statistics.export']);
        Sanctum::actingAs($user);

        $this->postJson('/api/attendance-statistics/simulate', [
            'academic_year_id' => $year->id, 'observed_present' => 75, 'observed_expected' => 100,
            'remaining_expected' => 20, 'future_rate' => 95, 'target_rate' => 85, 'method' => 'custom_scenario',
        ])->assertCreated()
            ->assertJsonPath('projection.projected_rate', 78.33)
            ->assertJsonPath('projection.target_is_mathematically_reachable', false);

        $response = $this->postJson('/api/attendance-statistics/exports', [
            'academic_year_id' => $year->id, 'report_type' => 'executive', 'format' => 'pdf',
            'filters' => ['academic_year_id' => $year->id, 'period' => 'academic_year'],
        ])->assertStatus(202)
            ->assertJsonPath('status', 'completed');
        $exportId = $response->json('id');
        $path = AttendanceExportJob::query()->findOrFail($exportId)->file_path;
        Storage::disk('local')->assertExists($path);
        $pdfContents = Storage::disk('local')->get($path);
        $this->assertStringStartsWith('%PDF-1.4', $pdfContents);
        $this->assertStringContainsString('/Helvetica-Bold', $pdfContents);
        $this->assertStringContainsString('Resumen ejecutivo', $pdfContents);
        $this->assertStringContainsString('Composici', $pdfContents);
        $this->assertGreaterThan(8000, strlen($pdfContents));
        $this->get('/api/attendance-statistics/exports/'.$exportId.'/download')->assertOk()->assertHeader('content-type', 'application/pdf');

        $excelResponse = $this->postJson('/api/attendance-statistics/exports', [
            'academic_year_id' => $year->id, 'report_type' => 'executive', 'format' => 'xls',
            'filters' => ['academic_year_id' => $year->id, 'period' => 'academic_year'],
        ])->assertStatus(202)
            ->assertJsonPath('status', 'completed');
        $excelExport = AttendanceExportJob::query()->findOrFail($excelResponse->json('id'));
        $workbook = Storage::disk('local')->get($excelExport->file_path);
        $this->assertStringContainsString('ss:Name="Filtros aplicados"', $workbook);
        $this->assertStringContainsString('ss:Name="Metodología"', $workbook);
        $this->assertStringContainsString('<FreezePanes/>', $workbook);
        $this->assertStringContainsString('<AutoFilter ', $workbook);
        $this->get('/api/attendance-statistics/exports/'.$excelExport->id.'/download')->assertOk()->assertHeader('content-type', 'application/vnd.ms-excel');
    }

    public function test_scheduled_reports_are_owned_and_enqueue_exports_when_due(): void
    {
        Storage::fake('local');
        Mail::fake();
        [$year, , , $user] = $this->scenario();
        $this->grant($user, ['attendance_statistics.manage_reports', 'attendance_statistics.view']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/attendance-statistics/scheduled-reports', [
            'academic_year_id' => $year->id, 'name' => 'Resumen semanal', 'report_type' => 'executive',
            'format' => 'pdf', 'frequency' => 'weekly', 'run_at' => '07:00',
            'next_run_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'filters' => ['academic_year_id' => $year->id], 'recipients' => [$user->email],
        ])->assertCreated()->assertJsonPath('name', 'Resumen semanal');

        $schedule = AttendanceScheduledReport::query()->findOrFail($response->json('id'));
        $schedule->update(['next_run_at' => now()->subMinute()]);
        $this->artisan('attendance:run-scheduled-reports')->assertSuccessful();

        $this->assertDatabaseHas('attendance_export_jobs', ['user_id' => $user->id, 'report_type' => 'executive', 'status' => 'completed']);
        $this->assertNotNull($schedule->fresh()->last_run_at);
        $this->deleteJson('/api/attendance-statistics/scheduled-reports/'.$schedule->id)->assertOk();
        $this->assertSoftDeleted('attendance_scheduled_reports', ['id' => $schedule->id]);
    }

    private function scenario(): array
    {
        $year = AcademicYear::query()->create(['name' => '2026', 'year' => 2026, 'starts_at' => '2026-03-01', 'ends_at' => '2026-12-20', 'is_active' => true, 'is_closed' => false]);
        $level = EducationLevel::query()->create(['name' => '1° Básico', 'order' => 1, 'type' => 'basica']);
        $course = CourseSection::query()->create(['academic_year_id' => $year->id, 'education_level_id' => $level->id, 'section_name' => 'A', 'display_name' => '1° Básico A', 'active' => true]);
        $student = StudentProfile::query()->create(['first_name' => 'Ana', 'last_name' => 'Demo', 'rut' => '11.111.111-1', 'general_status' => 'activo']);
        $enrollment = StudentEnrollment::query()->create(['student_profile_id' => $student->id, 'academic_year_id' => $year->id, 'course_section_id' => $course->id, 'enrollment_status' => 'regular', 'enrolled_at' => '2026-03-01', 'snapshot_year_name' => '2026', 'snapshot_level_name' => '1° Básico', 'snapshot_section_name' => 'A', 'snapshot_course_display_name' => '1° Básico A']);
        foreach ([['2026-03-02', 'present'], ['2026-03-03', 'present'], ['2026-03-04', 'absent'], ['2026-03-05', 'present']] as [$date, $status]) {
            $day = SchoolDay::query()->create(['academic_year_id' => $year->id, 'date' => $date, 'is_school_day' => true, 'status' => 'confirmed', 'source' => 'test']);
            AttendanceRecord::query()->create(['school_day_id' => $day->id, 'academic_year_id' => $year->id, 'course_section_id' => $course->id, 'student_profile_id' => $student->id, 'student_enrollment_id' => $enrollment->id, 'attendance_date' => $date, 'status' => $status, 'origin' => 'test']);
        }
        $user = User::factory()->create(['active' => true, 'user_type' => 'staff']);

        return [$year, $course, $student, $user];
    }

    private function grant(User $user, array $slugs): void
    {
        $role = Role::query()->firstOrCreate(['slug' => 'attendance-statistics-test'], ['name' => 'Estadísticas asistencia test', 'active' => true]);
        foreach ($slugs as $slug) {
            $permission = Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug, 'active' => true]);
            $role->permissions()->syncWithoutDetaching($permission->id);
        }
        $user->roles()->syncWithoutDetaching($role->id);
        $user->unsetRelation('roles');
    }
}
