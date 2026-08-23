<?php

namespace Tests\Feature\Infirmary;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\EducationLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InfirmaryDailyLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-21 11:30:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_registers_a_nursing_log_and_derives_the_students_current_course(): void
    {
        $user = $this->superAdmin();
        [$student, $currentCourse, $historicalCourse] = $this->studentWithCurrentAndHistoricalCourses();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/infirmary/daily-log', [
            ...$this->payload(),
            'student_profile_id' => $student->id,
            'course_section_id' => $historicalCourse->id,
        ])->assertCreated()
            ->assertJsonPath('data.student.id', $student->id)
            ->assertJsonPath('data.course_section.id', $currentCourse->id)
            ->assertJsonPath('data.registered_by.name', $user->name);

        $this->assertDatabaseHas('infirmary_daily_logs', [
            'id' => $response->json('data.id'),
            'student_profile_id' => $student->id,
            'course_section_id' => $currentCourse->id,
            'registered_by_user_id' => $user->id,
            'category' => 'derivacion_traslado',
            'requires_follow_up' => true,
        ]);

        $this->getJson('/api/infirmary/daily-log?search=Traslado')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('summary.total_records', 1)
            ->assertJsonPath('summary.today_records', 1)
            ->assertJsonPath('summary.pending_follow_up', 1)
            ->assertJsonPath('summary.high_priority', 1)
            ->assertJsonPath('capabilities.can_manage', true);
    }

    public function test_catalog_only_exposes_nursing_categories_and_current_calendar_year_courses(): void
    {
        $user = $this->superAdmin();
        [, $currentCourse, $historicalCourse] = $this->studentWithCurrentAndHistoricalCourses();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/infirmary/daily-log/catalogs')
            ->assertOk()
            ->assertJsonPath('current_academic_year.year', 2026)
            ->assertJsonCount(1, 'courses')
            ->assertJsonPath('courses.0.id', $currentCourse->id);

        $categories = collect($response->json('categories'))->pluck('value');
        $this->assertContains('atencion_relevante', $categories);
        $this->assertContains('bioseguridad', $categories);
        $this->assertNotContains('convivencia', $categories);
        $this->assertNotContains('asistencia', $categories);
        $this->assertNotContains($historicalCourse->id, collect($response->json('courses'))->pluck('id'));
    }

    public function test_inspectoria_fields_and_historical_general_courses_are_rejected(): void
    {
        $user = $this->superAdmin();
        [, , $historicalCourse] = $this->studentWithCurrentAndHistoricalCourses();
        Sanctum::actingAs($user);

        $this->postJson('/api/infirmary/daily-log', [
            ...$this->payload(),
            'category' => 'convivencia',
            'is_staff_lateness' => true,
            'late_staff_id' => 999,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('category');

        $this->postJson('/api/infirmary/daily-log', [
            ...$this->payload(),
            'student_profile_id' => null,
            'course_section_id' => $historicalCourse->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('course_section_id');

        $this->assertDatabaseCount('infirmary_daily_logs', 0);
    }

    public function test_daily_log_uses_specific_private_permissions(): void
    {
        $genericUser = $this->userWithPermissions(['ver_enfermeria']);
        Sanctum::actingAs($genericUser);
        $this->getJson('/api/infirmary/daily-log')->assertForbidden();

        $viewer = $this->userWithPermissions(['ver_bitacora_enfermeria']);
        Sanctum::actingAs($viewer);
        $this->getJson('/api/infirmary/daily-log')->assertOk();
        $this->postJson('/api/infirmary/daily-log', $this->payload())->assertForbidden();

        $manager = $this->userWithPermissions(['ver_bitacora_enfermeria', 'registrar_bitacora_enfermeria']);
        Sanctum::actingAs($manager);
        $this->postJson('/api/infirmary/daily-log', $this->payload())->assertCreated();
    }

    public function test_entry_can_be_updated_and_closing_it_clears_pending_follow_up(): void
    {
        $user = $this->superAdmin();
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/infirmary/daily-log', $this->payload())
            ->assertCreated()
            ->json('data.id');

        $this->putJson("/api/infirmary/daily-log/{$id}", [
            ...$this->payload(),
            'status' => 'cerrado',
            'requires_follow_up' => true,
            'follow_up_note' => 'Esta nota debe limpiarse al cerrar.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'cerrado')
            ->assertJsonPath('data.requires_follow_up', false)
            ->assertJsonPath('data.follow_up_note', null);

        $this->assertDatabaseHas('infirmary_daily_logs', [
            'id' => $id,
            'status' => 'cerrado',
            'requires_follow_up' => false,
            'follow_up_note' => null,
            'updated_by' => $user->id,
        ]);
    }

    /** @return array{StudentProfile, CourseSection, CourseSection} */
    private function studentWithCurrentAndHistoricalCourses(): array
    {
        $level = EducationLevel::query()->firstOrCreate(
            ['name' => '1° medio'],
            ['type' => 'media', 'order' => 90, 'active' => true],
        );
        $currentYear = AcademicYear::query()->firstOrCreate(
            ['year' => 2026],
            [
                'name' => 'Año escolar 2026',
                'starts_at' => '2026-03-01',
                'ends_at' => '2026-12-31',
                'is_active' => true,
                'is_closed' => false,
            ],
        );
        $historicalYear = AcademicYear::query()->firstOrCreate(
            ['year' => 2025],
            [
                'name' => 'Año escolar 2025',
                'starts_at' => '2025-03-01',
                'ends_at' => '2025-12-31',
                'is_active' => false,
                'is_closed' => true,
            ],
        );
        $currentCourse = CourseSection::query()->create([
            'academic_year_id' => $currentYear->id,
            'education_level_id' => $level->id,
            'section_name' => 'A',
            'display_name' => '1° medio A',
            'active' => true,
        ]);
        $historicalCourse = CourseSection::query()->create([
            'academic_year_id' => $historicalYear->id,
            'education_level_id' => $level->id,
            'section_name' => 'B',
            'display_name' => '1° medio B 2025',
            'active' => true,
        ]);
        $student = StudentProfile::query()->create([
            'first_name' => 'Antonia',
            'last_name' => 'Soto',
            'registered_name' => 'Antonia Soto',
            'rut' => '21.111.222-3',
            'general_status' => 'activo',
        ]);
        StudentEnrollment::query()->create([
            'student_profile_id' => $student->id,
            'academic_year_id' => $currentYear->id,
            'course_section_id' => $currentCourse->id,
            'enrollment_status' => 'regular',
            'enrolled_at' => '2026-03-01',
            'snapshot_year_name' => $currentYear->name,
            'snapshot_level_name' => $level->name,
            'snapshot_section_name' => 'A',
            'snapshot_course_display_name' => $currentCourse->display_name,
        ]);
        StudentEnrollment::query()->create([
            'student_profile_id' => $student->id,
            'academic_year_id' => $historicalYear->id,
            'course_section_id' => $historicalCourse->id,
            'enrollment_status' => 'regular',
            'enrolled_at' => '2025-03-01',
            'snapshot_year_name' => $historicalYear->name,
            'snapshot_level_name' => $level->name,
            'snapshot_section_name' => 'B',
            'snapshot_course_display_name' => $historicalCourse->display_name,
        ]);

        return [$student, $currentCourse, $historicalCourse];
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'student_profile_id' => null,
            'course_section_id' => null,
            'happened_at' => '2026-08-21 10:45:00',
            'category' => 'derivacion_traslado',
            'priority' => 'alta',
            'status' => 'en_seguimiento',
            'title' => 'Traslado coordinado a centro asistencial',
            'detail' => 'Se coordinó la continuidad de atención fuera del establecimiento.',
            'action_taken' => 'Se informó a dirección y se resguardó la documentación.',
            'requires_follow_up' => true,
            'follow_up_note' => 'Confirmar recepción del antecedente al término de la jornada.',
        ];
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super administrador', 'active' => true],
        );
        $user->roles()->attach($role);

        return $user;
    }

    /** @param array<int, string> $slugs */
    private function userWithPermissions(array $slugs): User
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->create([
            'name' => 'Rol '.implode('-', $slugs),
            'slug' => 'rol-'.str()->random(10),
            'active' => true,
        ]);
        $permissionIds = Permission::query()->whereIn('slug', $slugs)->pluck('id');
        $role->permissions()->sync($permissionIds);
        $user->roles()->attach($role);

        return $user;
    }
}
