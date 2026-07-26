<?php

namespace Tests\Feature\Staff;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_distinguishes_staff_profiles_with_and_without_access_accounts(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['ver_funcionarios']));

        $withAccount = Staff::query()->create([
            'full_name' => 'Funcionario con acceso',
            'status' => 'activo',
            'active' => true,
        ]);
        User::factory()->create([
            'staff_id' => $withAccount->id,
            'user_type' => 'staff',
            'active' => true,
        ]);
        Staff::query()->create([
            'full_name' => 'Funcionario sin acceso activo',
            'status' => 'activo',
            'active' => true,
        ]);
        Staff::query()->create([
            'full_name' => 'Funcionario sin acceso inactivo',
            'status' => 'inactivo',
            'active' => false,
        ]);

        $this->getJson('/api/staff?access=without_account&per_page=100')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('summary.total', 3)
            ->assertJsonPath('summary.active', 2)
            ->assertJsonPath('summary.with_account', 1)
            ->assertJsonPath('summary.without_account', 2);
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
