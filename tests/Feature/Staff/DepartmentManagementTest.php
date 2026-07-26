<?php

namespace Tests\Feature\Staff;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepartmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_creator_keeps_the_responsible_person_independent_from_the_team(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['administrar_departamentos']));

        $responsible = Staff::query()->create([
            'full_name' => 'Encargada Convivencia',
            'status' => 'activo',
            'active' => true,
        ]);
        $member = Staff::query()->create([
            'full_name' => 'Integrante Convivencia',
            'status' => 'activo',
            'active' => true,
        ]);

        $response = $this->postJson('/api/staff/departments', [
            'name' => 'Convivencia Escolar',
            'description' => 'Equipo de convivencia.',
            'responsible_staff_id' => $responsible->id,
            'staff_ids' => [$member->id],
            'active' => true,
            'color' => '#556ee6',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.staff_count', 1)
            ->assertJsonCount(1, 'data.staff');

        $department = Department::query()->where('name', 'Convivencia Escolar')->firstOrFail();

        $this->assertSame([$member->id], $department->staff()->pluck('staff.id')->all());
        $this->assertSame($responsible->id, $department->responsible_staff_id);
    }

    public function test_department_update_replaces_team_members_without_adding_the_responsible_person(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['administrar_departamentos']));

        $responsible = Staff::query()->create([
            'full_name' => 'Jefatura UTP',
            'status' => 'activo',
            'active' => true,
        ]);
        $oldMember = Staff::query()->create([
            'full_name' => 'Integrante anterior',
            'status' => 'activo',
            'active' => true,
        ]);
        $newMember = Staff::query()->create([
            'full_name' => 'Integrante actual',
            'status' => 'activo',
            'active' => true,
        ]);
        $department = Department::query()->create([
            'name' => 'Unidad Técnico Pedagógica',
            'slug' => 'unidad-tecnico-pedagogica',
            'responsible_staff_id' => $responsible->id,
            'active' => true,
        ]);
        $department->staff()->sync([$responsible->id, $oldMember->id]);

        $this->putJson("/api/staff/departments/{$department->id}", [
            'name' => $department->name,
            'responsible_staff_id' => $responsible->id,
            'staff_ids' => [$newMember->id],
            'active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.staff_count', 1);

        $this->assertSame([$newMember->id], $department->fresh()->staff()->pluck('staff.id')->all());
    }

    public function test_one_person_can_manage_multiple_departments_and_belong_to_a_different_team(): void
    {
        Sanctum::actingAs($this->userWithPermissions([
            'administrar_departamentos',
            'ver_funcionarios',
        ]));

        $administrator = Staff::query()->create([
            'full_name' => 'Administrador General',
            'status' => 'activo',
            'active' => true,
        ]);

        foreach (['Mantención', 'Recursos Humanos', 'Contabilidad'] as $name) {
            $this->postJson('/api/staff/departments', [
                'name' => $name,
                'responsible_staff_id' => $administrator->id,
                'staff_ids' => [],
                'active' => true,
            ])->assertCreated();
        }

        $directivo = $this->postJson('/api/staff/departments', [
            'name' => 'Equipo Directivo',
            'staff_ids' => [$administrator->id],
            'active' => true,
        ])->assertCreated();

        $this->assertSame(3, $administrator->managedDepartments()->count());
        $this->assertSame(
            ['Equipo Directivo'],
            $administrator->departments()->pluck('departments.name')->all(),
        );
        $directivo->assertJsonPath('data.staff_count', 1);

        $this->getJson("/api/staff/{$administrator->id}")
            ->assertOk()
            ->assertJsonCount(3, 'data.managed_departments')
            ->assertJsonCount(1, 'data.departments');
    }

    private function userWithPermissions(array $permissionSlugs): User
    {
        $role = Role::query()->create([
            'name' => 'Rol '.Str::random(8),
            'slug' => 'rol_'.Str::random(12),
            'active' => true,
        ]);

        $permissions = collect($permissionSlugs)->map(fn (string $slug) => Permission::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => Str::headline(str_replace('_', ' ', $slug)), 'active' => true],
        ));

        $role->permissions()->sync($permissions->pluck('id')->all());

        $user = User::factory()->create([
            'active' => true,
            'user_type' => 'staff',
        ]);
        $user->roles()->attach($role);

        return $user;
    }
}
