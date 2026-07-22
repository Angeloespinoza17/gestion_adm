<?php

namespace Tests\Feature\Staff;

use App\Models\DependencyReservation;
use App\Models\MaintenanceDependency;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_orphaned_staff_removes_its_restricting_reservations(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['eliminar_funcionarios']));
        [$staff, $reservation] = $this->createStaffWithReservation();

        $this->deleteJson("/api/staff/{$staff->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Funcionario y cuenta de acceso eliminados correctamente.');

        $this->assertDatabaseMissing('staff', ['id' => $staff->id]);
        $this->assertDatabaseMissing('dependency_reservations', ['id' => $reservation->id]);
    }

    public function test_deleting_staff_removes_its_linked_user_and_reservations_in_one_transaction(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['eliminar_funcionarios']));
        [$staff, $reservation] = $this->createStaffWithReservation();
        $linkedUser = User::factory()->create([
            'staff_id' => $staff->id,
            'user_type' => 'staff',
        ]);

        $this->deleteJson("/api/staff/{$staff->id}")->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $linkedUser->id]);
        $this->assertDatabaseMissing('staff', ['id' => $staff->id]);
        $this->assertDatabaseMissing('dependency_reservations', ['id' => $reservation->id]);
    }

    public function test_deleting_a_staff_user_from_admin_also_removes_the_staff_record(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['administrar_usuarios']));
        [$staff, $reservation] = $this->createStaffWithReservation();
        $linkedUser = User::factory()->create([
            'staff_id' => $staff->id,
            'user_type' => 'staff',
        ]);

        $this->deleteJson("/api/admin/users/{$linkedUser->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Usuario y ficha de funcionario eliminados correctamente.');

        $this->assertDatabaseMissing('users', ['id' => $linkedUser->id]);
        $this->assertDatabaseMissing('staff', ['id' => $staff->id]);
        $this->assertDatabaseMissing('dependency_reservations', ['id' => $reservation->id]);
    }

    public function test_bulk_user_deletion_also_removes_linked_staff_records(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['administrar_usuarios']));
        [$staff, $reservation] = $this->createStaffWithReservation();
        $linkedUser = User::factory()->create([
            'staff_id' => $staff->id,
            'user_type' => 'staff',
        ]);

        $this->deleteJson('/api/admin/users/bulk', [
            'users' => [$linkedUser->id],
        ])
            ->assertOk()
            ->assertJsonPath('deleted_count', 1)
            ->assertJsonPath('deleted_staff_count', 1);

        $this->assertDatabaseMissing('users', ['id' => $linkedUser->id]);
        $this->assertDatabaseMissing('staff', ['id' => $staff->id]);
        $this->assertDatabaseMissing('dependency_reservations', ['id' => $reservation->id]);
    }

    public function test_staff_endpoint_cannot_delete_a_linked_super_admin_account(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['eliminar_funcionarios']));
        [$staff, $reservation] = $this->createStaffWithReservation();
        $linkedUser = User::factory()->create([
            'staff_id' => $staff->id,
            'user_type' => 'staff',
        ]);
        $superAdminRole = Role::query()->create([
            'name' => 'Super Admin',
            'slug' => 'super_admin',
            'active' => true,
        ]);
        $linkedUser->roles()->attach($superAdminRole);

        $this->deleteJson("/api/staff/{$staff->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('staff');

        $this->assertDatabaseHas('users', ['id' => $linkedUser->id]);
        $this->assertDatabaseHas('staff', ['id' => $staff->id]);
        $this->assertDatabaseHas('dependency_reservations', ['id' => $reservation->id]);
    }

    /**
     * @return array{Staff, DependencyReservation}
     */
    private function createStaffWithReservation(): array
    {
        $staff = Staff::query()->create([
            'full_name' => 'Funcionario '.Str::random(8),
            'rut' => Str::random(8),
            'institutional_email' => Str::lower(Str::random(10)).'@example.test',
            'status' => 'activo',
            'active' => true,
        ]);
        $dependency = MaintenanceDependency::query()->create([
            'code' => 'DEP-'.Str::upper(Str::random(8)),
            'name' => 'Dependencia de prueba',
            'active' => true,
        ]);
        $reservation = DependencyReservation::query()->create([
            'maintenance_dependency_id' => $dependency->id,
            'staff_id' => $staff->id,
            'title' => 'Reserva de prueba',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => DependencyReservation::STATUS_APPROVED,
        ]);

        return [$staff, $reservation];
    }

    /**
     * @param  array<int, string>  $permissionSlugs
     */
    private function userWithPermissions(array $permissionSlugs): User
    {
        $role = Role::query()->create([
            'name' => 'Rol '.Str::random(8),
            'slug' => 'rol_'.Str::lower(Str::random(12)),
            'active' => true,
        ]);
        $permissions = collect($permissionSlugs)->map(fn (string $slug) => Permission::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => Str::headline(str_replace('_', ' ', $slug)), 'active' => true],
        ));
        $role->permissions()->sync($permissions->pluck('id')->all());

        $user = User::factory()->create(['active' => true]);
        $user->roles()->attach($role);

        return $user;
    }
}
