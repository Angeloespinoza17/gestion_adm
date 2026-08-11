<?php

namespace Tests\Feature\Spaces;

use App\Models\MaintenanceDependency;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaintenanceDependencyUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_can_be_saved_when_creating_and_editing_a_space_dependency(): void
    {
        Sanctum::actingAs($this->authorizedUser());

        $response = $this->postJson('/api/spaces/dependencies', [
            'code' => 'ESP-USO-20260810',
            'name' => 'Sala con uso definido',
            'usage' => '2° básico A',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.usage', '2° básico A');

        $dependencyId = $response->json('data.id');

        $this->assertDatabaseHas('maintenance_dependencies', [
            'id' => $dependencyId,
            'dependency_kind' => MaintenanceDependency::KIND_SPACE,
            'usage' => '2° básico A',
        ]);

        $this->putJson("/api/spaces/dependencies/{$dependencyId}", [
            'usage' => 'Sala multiuso',
        ])
            ->assertOk()
            ->assertJsonPath('data.usage', 'Sala multiuso');

        $this->assertDatabaseHas('maintenance_dependencies', [
            'id' => $dependencyId,
            'usage' => 'Sala multiuso',
        ]);
    }

    private function authorizedUser(): User
    {
        $user = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $role = Role::query()->create([
            'name' => 'Gestión de uso en dependencias',
            'slug' => 'gestion-uso-dependencias',
            'active' => true,
        ]);

        foreach (['crear_dependencias', 'editar_dependencias'] as $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => "Permiso {$slug}", 'active' => true]
            );
            $role->permissions()->attach($permission);
        }

        $user->roles()->attach($role);

        return $user;
    }
}
