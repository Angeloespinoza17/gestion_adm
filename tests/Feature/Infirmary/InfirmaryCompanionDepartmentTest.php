<?php

namespace Tests\Feature\Infirmary;

use App\Models\Cargo;
use App\Models\Department;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InfirmaryCompanionDepartmentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private StudentProfile $student;

    private Department $inspectorDepartment;

    private Department $classroomAssistantDepartment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['active' => true]);
        $role = Role::query()->create([
            'name' => 'Super administrador',
            'slug' => 'super_admin',
            'active' => true,
        ]);
        $this->user->roles()->attach($role);
        $this->student = StudentProfile::query()->create([
            'first_name' => 'Amalia',
            'last_name' => 'Mardones',
            'rut' => '27018009-4',
        ]);
        $this->inspectorDepartment = Department::query()->create([
            'name' => 'Inspectoría General',
            'slug' => 'inspectoria-general',
            'active' => true,
        ]);
        $this->classroomAssistantDepartment = Department::query()->create([
            'name' => 'Asistentes de aula',
            'slug' => 'asistentes-de-aula',
            'active' => true,
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_companion_catalog_is_grouped_by_staff_departments_instead_of_cargos(): void
    {
        $porterCargo = Cargo::query()->create([
            'name' => 'Portería',
            'slug' => 'porteria',
            'active' => true,
        ]);
        $inspectorCargo = Cargo::query()->create([
            'name' => 'Inspectora',
            'slug' => 'inspectoria',
            'active' => true,
        ]);

        $departmentMember = $this->staff('Integrante Inspectoría', $porterCargo);
        $departmentResponsible = $this->staff('Responsable Inspectoría', $porterCargo);
        $cargoOnly = $this->staff('Inspectora sin departamento', $inspectorCargo);
        $inactiveMember = $this->staff('Integrante inactiva', $inspectorCargo, false);

        $this->inspectorDepartment->staff()->attach([$departmentMember->id, $inactiveMember->id]);
        $this->inspectorDepartment->update(['responsible_staff_id' => $departmentResponsible->id]);

        $response = $this->getJson('/api/infirmary/catalogs')->assertOk();
        $inspectors = collect($response->json('companion_staff.inspectora'));

        $this->assertSame(
            [$departmentMember->id, $departmentResponsible->id],
            $inspectors->pluck('id')->sort()->values()->all(),
        );
        $this->assertSame(
            ['Inspectoría General'],
            $inspectors->firstWhere('id', $departmentMember->id)['department_names'],
        );
        $this->assertFalse($inspectors->contains('id', $cargoOnly->id));
        $this->assertFalse($inspectors->contains('id', $inactiveMember->id));
        $response->assertJsonPath('companion_staff.asistente_aula', []);
    }

    public function test_attention_accepts_only_active_staff_from_the_selected_companion_department(): void
    {
        $otherCargo = Cargo::query()->create([
            'name' => 'Asistente de convivencia escolar',
            'slug' => 'asistente-de-convivencia-escolar',
            'active' => true,
        ]);
        $departmentMember = $this->staff('Asistente asociada al departamento', $otherCargo);
        $staffWithoutDepartment = $this->staff('Asistente sin departamento', $otherCargo);
        $this->classroomAssistantDepartment->staff()->attach($departmentMember->id);

        $this->postJson('/api/infirmary/attentions', $this->attentionPayload($departmentMember))
            ->assertCreated()
            ->assertJsonPath('data.accompanied_by_staff_id', $departmentMember->id);

        $this->postJson('/api/infirmary/attentions', $this->attentionPayload($staffWithoutDepartment))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('accompanied_by_staff_id');
    }

    private function staff(string $name, Cargo $cargo, bool $active = true): Staff
    {
        return Staff::query()->create([
            'full_name' => $name,
            'rut' => fake()->unique()->numerify('########-#'),
            'cargo_id' => $cargo->id,
            'status' => $active ? 'activo' : 'inactivo',
            'active' => $active,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function attentionPayload(Staff $companion): array
    {
        return [
            'student_profile_id' => $this->student->id,
            'attention_category' => 'dolor_cabeza',
            'occurred_at' => now()->subMinutes(10)->format('Y-m-d H:i:s'),
            'attended_at' => now()->subMinutes(5)->format('Y-m-d H:i:s'),
            'accompanied_by_type' => 'asistente_aula',
            'accompanied_by_staff_id' => $companion->id,
            'consultation_reason' => 'Dolor de cabeza',
            'priority' => 'media',
            'status' => 'abierta',
        ];
    }
}
