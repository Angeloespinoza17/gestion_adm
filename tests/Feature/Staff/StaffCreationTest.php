<?php

namespace Tests\Feature\Staff;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_staff_also_creates_staff_user_with_rut_password(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['gestionar_funcionarios']));

        $response = $this->postJson('/api/staff', [
            'full_name' => 'María Prueba Funcionario',
            'rut' => '12.345.678-5',
            'institutional_email' => 'maria.prueba@cnscgestion.local',
            'status' => 'activo',
            'active' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.full_name', 'María Prueba Funcionario')
            ->assertJsonPath('data.rut', '12345678-5')
            ->assertJsonPath('data.user.email', 'maria.prueba@cnscgestion.local');

        $staff = Staff::query()->where('rut', '12345678-5')->firstOrFail();
        $user = User::query()->where('staff_id', $staff->id)->firstOrFail();

        $this->assertSame('María Prueba Funcionario', $user->name);
        $this->assertSame('maria.prueba@cnscgestion.local', $user->email);
        $this->assertSame('staff', $user->user_type);
        $this->assertTrue($user->active);
        $this->assertTrue(Hash::check('12345678-5', $user->password));
    }

    public function test_creating_staff_does_not_validate_rut_check_digit(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['gestionar_funcionarios']));

        $response = $this->postJson('/api/staff', [
            'full_name' => 'Funcionario Rut Flexible',
            'rut' => '12.345.678-9',
            'institutional_email' => 'rut.flexible@cnscgestion.local',
            'status' => 'activo',
            'active' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.rut', '12345678-9')
            ->assertJsonPath('data.user.email', 'rut.flexible@cnscgestion.local');

        $staff = Staff::query()->where('rut', '12345678-9')->firstOrFail();
        $user = User::query()->where('staff_id', $staff->id)->firstOrFail();

        $this->assertTrue(Hash::check('12345678-9', $user->password));
    }

    private function userWithPermissions(array $permissionSlugs): User
    {
        $role = Role::query()->create([
            'name' => 'Rol ' . Str::random(8),
            'slug' => 'rol_' . Str::random(12),
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
