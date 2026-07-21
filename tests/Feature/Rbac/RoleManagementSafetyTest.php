<?php

namespace Tests\Feature\Rbac;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleManagementSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_access_is_saved_in_one_validated_request(): void
    {
        $this->authenticateRoleAdministrator();
        $permission = Permission::query()->create(['name' => 'Ver pruebas', 'slug' => 'ver_pruebas', 'active' => true]);
        $module = SystemModule::query()->create(['name' => 'Pruebas', 'slug' => 'pruebas', 'frontend_route' => '/pruebas', 'active' => true]);

        $response = $this->postJson('/api/admin/roles', [
            'name' => 'Rol de Pruebas',
            'slug' => 'ROL PRUEBAS',
            'active' => true,
            'permissions' => [$permission->id],
            'modules' => [$module->id],
        ]);

        $response->assertCreated()->assertJsonPath('data.slug', 'rol_pruebas');
        $role = Role::query()->where('slug', 'rol_pruebas')->firstOrFail();
        $this->assertTrue($role->permissions()->whereKey($permission->id)->exists());
        $this->assertTrue($role->modules()->whereKey($module->id)->exists());
    }

    public function test_validation_failure_does_not_partially_update_a_role(): void
    {
        $this->authenticateRoleAdministrator();
        $originalPermission = Permission::query()->create(['name' => 'Original', 'slug' => 'permiso_original', 'active' => true]);
        $newPermission = Permission::query()->create(['name' => 'Nuevo', 'slug' => 'permiso_nuevo', 'active' => true]);
        $role = Role::query()->create(['name' => 'Original', 'slug' => 'original', 'active' => true]);
        $role->permissions()->attach($originalPermission);

        $this->putJson("/api/admin/roles/{$role->id}", [
            'name' => 'Nombre modificado',
            'slug' => 'original',
            'active' => true,
            'permissions' => [$newPermission->id],
            'modules' => [999999],
        ])->assertUnprocessable();

        $role->refresh();
        $this->assertSame('Original', $role->name);
        $this->assertEqualsCanonicalizing([$originalPermission->id], $role->permissions()->pluck('permissions.id')->all());
    }

    public function test_super_admin_and_roles_with_users_are_protected(): void
    {
        $this->authenticateRoleAdministrator();
        $superAdmin = Role::query()->create(['name' => 'Super Admin', 'slug' => 'super_admin', 'active' => true]);
        $role = Role::query()->create(['name' => 'Enfermería', 'slug' => 'enfermeria', 'active' => true]);
        $user = User::factory()->create(['active' => true]);
        $role->users()->attach($user);

        $this->deleteJson("/api/admin/roles/{$superAdmin->id}")->assertUnprocessable();
        $this->deleteJson("/api/admin/roles/{$role->id}")->assertUnprocessable();
        $this->putJson("/api/admin/roles/{$role->id}", [
            'permissions' => [],
        ])->assertUnprocessable();
    }

    public function test_sensitive_endpoints_are_not_public_and_deploy_is_superadmin_only(): void
    {
        $this->postJson('/api/register', [])->assertNotFound();
        $this->getJson('/api/_debug/auth')->assertNotFound();
        $this->getJson('/api/deploy/status')->assertUnauthorized();
        $this->get('/api/deploy/status')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['active' => true]));
        $this->getJson('/api/deploy/status')->assertForbidden();
        $this->postJson('/api/deploy')->assertForbidden();

        $superAdmin = User::factory()->create(['active' => true]);
        $superAdminRole = Role::query()->create(['name' => 'Super Admin', 'slug' => 'super_admin', 'active' => true]);
        $superAdmin->roles()->attach($superAdminRole);
        Sanctum::actingAs($superAdmin);

        $this->getJson('/api/deploy/status')
            ->assertOk()
            ->assertJsonStructure(['enabled', 'configured', 'target', 'path']);
    }

    private function authenticateRoleAdministrator(): User
    {
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'administrar_roles'],
            ['name' => 'Administrar roles', 'active' => true],
        );
        $role = Role::query()->firstOrCreate(
            ['slug' => 'administrador_pruebas'],
            ['name' => 'Administrador de pruebas', 'active' => true],
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user = User::factory()->create(['active' => true]);
        $user->roles()->attach($role);
        Sanctum::actingAs($user);

        return $user;
    }
}
