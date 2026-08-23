<?php

namespace Tests\Feature\Attendance;

use App\Models\AcademicYear;
use App\Models\Attendance\AttendanceAbsenceReason;
use App\Models\Attendance\AttendanceExportJob;
use App\Models\Attendance\AttendanceInterventionType;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Attendance\AttendanceRiskSnapshot;
use App\Models\Attendance\SchoolDay;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceManagementModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_uses_only_confirmed_school_days_inside_enrollment_validity(): void
    {
        [$year, $course, $student] = $this->scenario();

        $this->artisan('attendance:analyze', ['--academic-year-id' => $year->id, '--date' => '2026-03-12'])
            ->assertSuccessful();

        $snapshot = AttendanceRiskSnapshot::query()->where('student_profile_id', $student->id)->firstOrFail();
        $this->assertSame(7, $snapshot->school_days_elapsed);
        $this->assertSame(4, $snapshot->days_present);
        $this->assertSame(3, $snapshot->days_absent);
        $this->assertSame(2, $snapshot->unjustified_absences);
        $this->assertEquals(57.14, $snapshot->attendance_percentage);
        $this->assertSame('red', $snapshot->risk_level);
        $this->assertDatabaseCount('attendance_risk_snapshots', 1);
    }

    public function test_end_to_end_case_plan_and_individual_pdf_are_authorized_and_audited(): void
    {
        Storage::fake('local');
        [$year, $course, $student] = $this->scenario();
        $user = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $this->grant($user, [
            'attendance_management.view', 'attendance_management.view_all', 'attendance_management.manage_cases',
            'attendance_management.manage_interventions', 'attendance_management.manage_causes',
            'attendance_management.manage_action_plans', 'attendance_management.export',
            'attendance_management.view_sensitive', 'attendance_management.configure',
        ]);
        Sanctum::actingAs($user);
        $this->artisan('attendance:analyze', ['--academic-year-id' => $year->id, '--date' => '2026-03-12'])->assertSuccessful();

        $this->getJson('/api/attendance-management/dashboard?academic_year_id='.$year->id)
            ->assertOk()->assertJsonPath('meta.source', 'attendance_records + school_days + student_enrollments (sin duplicar asistencia)')
            ->assertJsonPath('kpis.red_students', 1);
        $this->getJson('/api/attendance-management/students/'.$student->id.'?academic_year_id='.$year->id)
            ->assertOk()->assertJsonPath('summary.school_days_elapsed', 7)->assertJsonPath('risk.level', 'red');
        $configuration = $this->getJson('/api/attendance-management/configuration?academic_year_id='.$year->id)
            ->assertOk()->assertJsonPath('settings.risk_thresholds.green', 95)->json('settings');
        $this->putJson('/api/attendance-management/configuration/settings', [
            'academic_year_id' => $year->id,
            ...$configuration,
            'reason' => 'Verificación automatizada de la configuración anual.',
        ])->assertOk()->assertJsonPath('settings.risk_thresholds.green', 95);

        $caseResponse = $this->postJson('/api/attendance-management/cases', [
            'student_profile_id' => $student->id, 'academic_year_id' => $year->id, 'course_section_id' => $course->id,
            'responsible_user_id' => $user->id, 'priority' => 'high',
            'initial_situation' => 'Asistencia acumulada bajo el umbral institucional.',
            'reason' => 'Apertura preventiva validada por inspectoría.',
        ])->assertCreated()->assertJsonPath('status', 'detected');
        $caseId = $caseResponse->json('id');

        $reason = AttendanceAbsenceReason::query()->create(['code' => 'test_family', 'name' => 'Situación familiar', 'category' => 'familia', 'is_sensitive' => true, 'active' => true, 'sort_order' => 999]);
        $this->postJson('/api/attendance-management/cases/'.$caseId.'/causes', [
            'absence_reason_id' => $reason->id, 'is_primary' => true, 'information_source' => 'family',
            'identified_on' => '2026-03-12', 'observations' => 'Antecedente reservado.', 'is_sensitive' => true,
        ])->assertCreated();
        $this->postJson('/api/attendance-management/cases/'.$caseId.'/notes', [
            'note' => 'Nota general de seguimiento.', 'is_sensitive' => false,
        ])->assertCreated();
        $this->postJson('/api/attendance-management/cases/'.$caseId.'/notes', [
            'note' => 'Nota reservada para el equipo autorizado.', 'is_sensitive' => true,
        ])->assertCreated();
        $this->postJson('/api/attendance-management/cases/'.$caseId.'/family-contacts', [
            'contacted_at' => '2026-03-12 09:00:00', 'channel' => 'phone', 'contacted_person' => 'Apoderado',
            'result' => 'successful', 'observation' => 'Se acuerda entrevista.', 'responsible_user_id' => $user->id,
        ])->assertCreated();
        $this->postJson('/api/attendance-management/cases/'.$caseId.'/agreements', [
            'meeting_date' => '2026-03-12', 'agreement' => 'Informar oportunamente cualquier inasistencia.',
            'responsible_user_id' => $user->id, 'commitment_date' => '2026-03-19',
        ])->assertCreated();
        $type = AttendanceInterventionType::query()->create(['code' => 'test_monitoring', 'name' => 'Monitoreo', 'category' => 'seguimiento', 'active' => true, 'sort_order' => 999]);
        $this->postJson('/api/attendance-management/cases/'.$caseId.'/interventions', [
            'intervention_type_id' => $type->id, 'responsible_user_id' => $user->id,
            'opened_at' => '2026-03-12 10:00:00', 'description' => 'Monitoreo semanal y coordinación con profesor jefe.',
            'status' => 'intervention',
        ])->assertCreated();
        $planResponse = $this->postJson('/api/attendance-management/cases/'.$caseId.'/action-plans', [
            'initial_situation' => 'Asistencia acumulada bajo el umbral.', 'objective' => 'Recuperar asistencia sostenida.',
            'goal_type' => 'attendance_rate', 'goal_value' => 90, 'goal_window_days' => 30,
            'starts_on' => '2026-03-12', 'review_on' => '2026-04-15', 'responsible_user_id' => $user->id,
            'actions' => [['title' => 'Revisión semanal', 'frequency' => 'weekly', 'due_at' => '2026-03-19 09:00:00']],
        ])->assertCreated()->assertJsonPath('status', 'active');
        $actionId = $planResponse->json('actions.0.id');
        $this->patchJson('/api/attendance-management/action-plan-actions/'.$actionId, [
            'status' => 'completed', 'result' => 'Revisión realizada y registrada.',
            'reason' => 'Cumplimiento verificado por el responsable.',
        ])->assertOk()->assertJsonPath('status', 'completed');
        $this->postJson('/api/attendance-management/action-plans/'.$planResponse->json('id').'/evaluate', [
            'reason' => 'Evaluación de seguimiento con los datos oficiales disponibles.',
        ])->assertOk()->assertJsonStructure(['evaluation_result', 'result_variation']);

        $exportResponse = $this->postJson('/api/attendance-statistics/exports', [
            'academic_year_id' => $year->id, 'report_type' => 'individual', 'format' => 'pdf',
            'filters' => ['academic_year_id' => $year->id, 'student_profile_id' => $student->id],
        ])->assertStatus(202)->assertJsonPath('status', 'completed');
        $export = AttendanceExportJob::query()->findOrFail($exportResponse->json('id'));
        Storage::disk('local')->assertExists($export->file_path);
        $this->assertStringStartsWith('%PDF-1.4', Storage::disk('local')->get($export->file_path));
        $this->assertDatabaseHas('attendance_statistics_audit_logs', ['action' => 'attendance_case_created', 'auditable_id' => $caseId]);
        $this->assertDatabaseHas('attendance_statistics_audit_logs', ['action' => 'attendance_action_plan_evaluated', 'auditable_id' => $planResponse->json('id')]);
        $this->assertDatabaseHas('attendance_statistics_audit_logs', ['action' => 'attendance_case_note_created']);
        $this->assertDatabaseHas('attendance_statistics_audit_logs', ['action' => 'attendance_action_plan_action_updated', 'auditable_id' => $actionId]);
        $this->assertDatabaseHas('attendance_statistics_audit_logs', ['action' => 'attendance_management_settings_updated']);

        $viewer = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $this->grant($viewer, ['attendance_management.view', 'attendance_management.view_all']);
        Sanctum::actingAs($viewer);
        $this->getJson('/api/attendance-management/cases?academic_year_id='.$year->id)
            ->assertOk()->assertJsonMissingPath('data.0.plans');
        $this->getJson('/api/attendance-management/cases/'.$caseId)
            ->assertOk()
            ->assertJsonCount(1, 'notes')
            ->assertJsonCount(0, 'causes')
            ->assertJsonPath('family_contacts.0.observation', null)
            ->assertJsonPath('interventions.0.description', null)
            ->assertJsonPath('plans.0.initial_situation', null);
    }

    public function test_course_scoped_viewer_cannot_read_unassigned_students(): void
    {
        [$year, , $student] = $this->scenario();
        $user = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $this->grant($user, ['attendance_management.view']);
        Sanctum::actingAs($user);

        $this->getJson('/api/attendance-management/students/'.$student->id.'?academic_year_id='.$year->id)->assertForbidden();
        $this->getJson('/api/attendance-management/dashboard?academic_year_id='.$year->id)
            ->assertOk()->assertJsonPath('kpis.red_students', 0);
    }

    private function scenario(): array
    {
        $year = AcademicYear::query()->create(['name' => 'Año pruebas 2026', 'year' => 2096, 'starts_at' => '2026-03-01', 'ends_at' => '2026-12-20', 'is_active' => true, 'is_closed' => false]);
        $order = ((int) EducationLevel::query()->max('order')) + 100;
        $level = EducationLevel::query()->create(['name' => 'Nivel pruebas '.$order, 'order' => $order, 'type' => 'basica']);
        $course = CourseSection::query()->create(['academic_year_id' => $year->id, 'education_level_id' => $level->id, 'section_name' => 'Z', 'display_name' => 'Nivel pruebas Z', 'active' => true]);
        $student = StudentProfile::query()->create(['first_name' => 'Amanda', 'last_name' => 'Prueba', 'rut' => '19.999.999-9', 'general_status' => 'activo']);
        $enrollment = StudentEnrollment::query()->create([
            'student_profile_id' => $student->id, 'academic_year_id' => $year->id, 'course_section_id' => $course->id,
            'enrollment_status' => 'regular', 'enrolled_at' => '2026-03-03', 'withdrawn_at' => '2026-03-10',
            'snapshot_year_name' => '2026', 'snapshot_level_name' => $level->name, 'snapshot_section_name' => 'Z', 'snapshot_course_display_name' => $course->display_name,
        ]);
        $records = [
            ['2026-03-01', 'present', true, false], ['2026-03-03', 'present', true, false],
            ['2026-03-04', 'absent', true, false], ['2026-03-05', 'absent', true, false],
            ['2026-03-06', 'absent', true, true], ['2026-03-07', 'absent', false, false],
            ['2026-03-08', 'present', true, false], ['2026-03-09', 'present', true, false],
            ['2026-03-10', 'present', true, false], ['2026-03-11', 'absent', true, false],
        ];
        foreach ($records as [$date, $status, $schoolDay, $justified]) {
            $day = SchoolDay::query()->create(['academic_year_id' => $year->id, 'date' => $date, 'is_school_day' => $schoolDay, 'status' => 'confirmed', 'source' => 'test']);
            AttendanceRecord::query()->create([
                'school_day_id' => $day->id, 'academic_year_id' => $year->id, 'course_section_id' => $course->id,
                'student_profile_id' => $student->id, 'student_enrollment_id' => $enrollment->id,
                'attendance_date' => $date, 'status' => $status, 'origin' => 'test', 'is_justified' => $justified,
            ]);
        }

        return [$year, $course, $student];
    }

    private function grant(User $user, array $slugs): void
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => 'attendance-management-test-'.$user->id],
            ['name' => 'Gestión asistencia test '.$user->id, 'active' => true],
        );
        foreach ($slugs as $slug) {
            $permission = Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug, 'active' => true]);
            $role->permissions()->syncWithoutDetaching($permission->id);
        }
        $user->roles()->syncWithoutDetaching($role->id);
        $user->unsetRelation('roles');
    }
}
