<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_creator_does_not_create_a_staff_category_without_a_staff_profile(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['administrar_usuarios']));

        $this->postJson('/api/admin/users', [
            'name' => 'Cuenta sin ficha',
            'email' => 'cuenta.sin.ficha@example.test',
            'password' => 'password-segura',
            'user_type' => 'staff',
            'active' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_type');

        $this->assertDatabaseMissing('users', [
            'email' => 'cuenta.sin.ficha@example.test',
        ]);
    }

    public function test_linked_staff_user_cannot_be_reclassified_as_a_student(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['administrar_usuarios']));

        $staff = Staff::query()->create([
            'full_name' => 'Funcionario categorizado',
            'status' => 'activo',
            'active' => true,
        ]);
        $user = User::factory()->create([
            'staff_id' => $staff->id,
            'user_type' => 'staff',
            'active' => true,
        ]);

        $this->putJson("/api/admin/users/{$user->id}", [
            'user_type' => 'student',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_type');

        $this->assertSame('staff', $user->fresh()->user_type);
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
