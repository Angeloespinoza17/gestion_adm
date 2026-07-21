<?php

namespace Tests\Feature\Attendance;

use App\Models\AcademicYear;
use App\Models\Attendance\AttendanceAlert;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Attendance\SchoolDay;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_the_attendance_permission_and_returns_one_consolidated_payload(): void
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
        $student = StudentProfile::query()->create([
            'first_name' => 'Elena', 'last_name' => 'Prueba', 'rut' => '22.222.222-2',
            'general_status' => 'activo',
        ]);
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
        AttendanceAlert::query()->create([
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'type' => 'low_attendance',
            'severity' => 'critical',
            'status' => 'open',
            'detected_on' => '2026-04-30',
            'title' => 'Asistencia bajo el umbral',
            'description' => 'La asistencia acumulada es inferior al objetivo.',
        ]);
        AttendanceAlert::query()->create([
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'type' => 'consecutive_absences',
            'severity' => 'warning',
            'status' => 'in_progress',
            'detected_on' => '2026-05-05',
            'title' => 'Ausencias consecutivas',
            'description' => 'Se detectaron ausencias consecutivas.',
        ]);
        $user = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        Sanctum::actingAs($user);

        $this->getJson('/api/students/attendance/dashboard?academic_year_id='.$year->id)
            ->assertForbidden();

        $role = Role::query()->create(['name' => 'Asistencia', 'slug' => 'asistencia', 'active' => true]);
        $permission = Permission::query()->create(['name' => 'Ver asistencia', 'slug' => 'ver_asistencia', 'active' => true]);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $this->getJson('/api/students/attendance/dashboard?academic_year_id='.$year->id)
            ->assertOk()
            ->assertJsonStructure([
                'meta' => ['academic_year', 'date_range', 'capabilities'],
                'catalogs' => ['academic_years', 'courses'],
                'summary', 'daily', 'calendar', 'courses', 'students', 'alerts', 'projections', 'imports',
            ])
            ->assertJsonPath('summary.school_days', 0)
            ->assertJsonPath('summary.open_alerts', 2)
            ->assertJsonPath('alerts.0.course_id', $course->id)
            ->assertJsonPath('alerts.0.total', 2)
            ->assertJsonPath('alerts.0.critical', 1)
            ->assertJsonPath('students.0.course_id', $course->id)
            ->assertJsonPath('students.0.students', 1)
            ->assertJsonPath('students.0.without_data', 1)
            ->assertJsonCount(1, 'courses');

        $this->getJson('/api/students/attendance/students?academic_year_id='.$year->id.'&course_section_id='.$course->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('group.students', 1)
            ->assertJsonPath('data.0.id', $student->id)
            ->assertJsonPath('data.0.attendance_rate', null)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'rut', 'course_id', 'course', 'present', 'absent', 'total', 'attendance_rate', 'remaining_allowed_absences']],
                'group' => ['key', 'course_id', 'course', 'students', 'with_data', 'without_data', 'below_target', 'average_attendance'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
            ]);

        $this->getJson('/api/students/attendance/alerts?academic_year_id='.$year->id.'&course_section_id='.$course->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('group.total', 2)
            ->assertJsonPath('data.0.severity', 'critical')
            ->assertJsonStructure([
                'data' => [['id', 'type', 'severity', 'status', 'title', 'description', 'detected_on', 'course', 'course_id', 'followups_count']],
                'group' => ['key', 'course_id', 'course', 'total', 'critical', 'warning', 'students', 'types'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
            ]);
    }

    public function test_authorized_user_can_confirm_an_anomaly_and_correct_an_attendance_record(): void
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
        $student = StudentProfile::query()->create([
            'first_name' => 'Ana', 'last_name' => 'Prueba', 'rut' => '11.111.111-1',
            'general_status' => 'activo',
        ]);
        $enrollment = StudentEnrollment::query()->create([
            'student_profile_id' => $student->id,
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'enrollment_status' => 'regular',
            'snapshot_year_name' => '2026',
            'snapshot_level_name' => '2º Básico',
            'snapshot_section_name' => 'A',
            'snapshot_course_display_name' => '2º Básico A',
        ]);
        $day = SchoolDay::query()->create([
            'academic_year_id' => $year->id,
            'date' => '2026-04-14',
            'is_school_day' => true,
            'status' => 'pending_confirmation',
            'source' => 'attendance_import',
        ]);
        $record = AttendanceRecord::query()->create([
            'school_day_id' => $day->id,
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'student_profile_id' => $student->id,
            'student_enrollment_id' => $enrollment->id,
            'attendance_date' => '2026-04-14',
            'status' => AttendanceRecord::ABSENT,
            'origin' => 'import',
        ]);
        $user = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $role = Role::query()->create(['name' => 'Editor asistencia', 'slug' => 'editor-asistencia', 'active' => true]);
        $permission = Permission::query()->create(['name' => 'Editar asistencia', 'slug' => 'editar_asistencia', 'active' => true]);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
        Sanctum::actingAs($user);

        $this->patchJson('/api/students/attendance/school-days/'.$day->id, ['status' => 'confirmed'])
            ->assertOk()
            ->assertJsonPath('status', 'confirmed');
        $this->patchJson('/api/students/attendance/records/'.$record->id, [
            'status' => AttendanceRecord::PRESENT,
            'notes' => 'Corrección verificada.',
        ])->assertOk()
            ->assertJsonPath('status', AttendanceRecord::PRESENT)
            ->assertJsonPath('origin', 'manual');

        $this->assertDatabaseHas('school_days', ['id' => $day->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('attendance_records', [
            'id' => $record->id,
            'status' => AttendanceRecord::PRESENT,
            'updated_by' => $user->id,
        ]);
    }
}
