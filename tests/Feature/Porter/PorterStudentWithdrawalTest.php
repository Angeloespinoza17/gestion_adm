<?php

namespace Tests\Feature\Porter;

use App\Models\AcademicYear;
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

class PorterStudentWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_a_regular_withdrawal_for_an_authorized_guardian(): void
    {
        $user = $this->createUserWithPermissions(['ver_porteria', 'registrar_retiro_porteria']);
        [$student, $course, $year] = $this->createActiveStudent();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/porter/withdrawals', [
            'student_profile_id' => $student->id,
            'person_name' => 'María Guardia',
            'person_rut' => '12.345.678-5',
            'person_relationship' => 'apoderado',
            'person_phone' => '999999999',
            'reason' => 'medico',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'registrado')
            ->assertJsonPath('data.requires_special_authorization', false);

        $this->assertDatabaseHas('porter_student_withdrawals', [
            'student_profile_id' => $student->id,
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'status' => 'registrado',
            'person_authorized' => 1,
        ]);

        $this->assertDatabaseCount('porter_authorization_requests', 0);
    }

    public function test_it_creates_an_authorization_request_when_the_person_is_not_authorized(): void
    {
        $user = $this->createUserWithPermissions(['ver_porteria', 'registrar_retiro_porteria']);
        [$student] = $this->createActiveStudent();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/porter/withdrawals', [
            'student_profile_id' => $student->id,
            'person_name' => 'Persona No Autorizada',
            'person_relationship' => 'otro',
            'person_phone' => '999999999',
            'reason' => 'otro',
            'observations' => 'Se solicita revisión.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'observado')
            ->assertJsonPath('data.requires_special_authorization', true);

        $this->assertDatabaseHas('porter_student_withdrawals', [
            'student_profile_id' => $student->id,
            'status' => 'observado',
            'person_authorized' => 0,
        ]);

        $this->assertDatabaseHas('porter_authorization_requests', [
            'status' => 'pendiente',
            'required_permission_slug' => 'autorizar_retiros_porteria',
        ]);
    }

    public function test_it_requires_confirmation_for_recent_duplicate_withdrawals(): void
    {
        $user = $this->createUserWithPermissions(['ver_porteria', 'registrar_retiro_porteria']);
        [$student] = $this->createActiveStudent();

        Sanctum::actingAs($user);

        $payload = [
            'student_profile_id' => $student->id,
            'person_name' => 'María Guardia',
            'person_rut' => '12.345.678-5',
            'person_relationship' => 'apoderado',
            'reason' => 'medico',
        ];

        $this->postJson('/api/porter/withdrawals', $payload)->assertCreated();

        $response = $this->postJson('/api/porter/withdrawals', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['force_duplicate_confirmation']);
    }

    private function createUserWithPermissions(array $permissionSlugs): User
    {
        $user = User::factory()->create([
            'active' => true,
            'name' => 'Portería Test',
        ]);

        $permissions = collect($permissionSlugs)->map(function ($slug) {
            return Permission::query()->create([
                'slug' => $slug,
                'name' => ucfirst(str_replace('_', ' ', $slug)),
                'active' => true,
            ]);
        });

        $role = Role::query()->create([
            'name' => 'Portería Test',
            'slug' => 'porteria_test_' . uniqid(),
            'active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function createActiveStudent(): array
    {
        $year = AcademicYear::query()->create([
            'name' => '2026',
            'year' => 2026,
            'starts_at' => '2026-03-01',
            'ends_at' => '2026-12-31',
            'is_active' => true,
            'is_closed' => false,
        ]);

        $level = EducationLevel::query()->create([
            'name' => '7° básico',
            'order' => 1,
            'type' => 'basica',
        ]);

        $course = CourseSection::query()->create([
            'academic_year_id' => $year->id,
            'education_level_id' => $level->id,
            'section_name' => 'A',
            'display_name' => '7° básico A',
            'capacity' => 30,
            'active' => true,
        ]);

        $student = StudentProfile::query()->create([
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'rut' => '22333444-5',
            'general_status' => 'activo',
            'guardian_name' => 'María Guardia',
            'guardian_rut' => '12345678-5',
            'guardian_phone' => '987654321',
            'authorized_pickup_people' => [
                [
                    'name' => 'Tía Rosa',
                    'rut' => '76086428-5',
                    'relationship' => 'familiar',
                    'phone' => '911111111',
                ],
            ],
        ]);

        StudentEnrollment::query()->create([
            'student_profile_id' => $student->id,
            'academic_year_id' => $year->id,
            'course_section_id' => $course->id,
            'enrollment_status' => 'regular',
            'enrolled_at' => '2026-03-01',
            'snapshot_year_name' => $year->name,
            'snapshot_level_name' => $level->name,
            'snapshot_section_name' => 'A',
            'snapshot_course_display_name' => $course->display_name,
        ]);

        return [$student, $course, $year];
    }
}
