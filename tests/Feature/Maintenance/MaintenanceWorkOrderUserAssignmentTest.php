<?php

namespace Tests\Feature\Maintenance;

use App\Models\MaintenanceWorkOrder;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaintenanceWorkOrderUserAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_indexes_sebastian_matamala_to_an_active_real_user(): void
    {
        $creator = $this->authorizedUser('Usuario creador', ['ver_mantencion']);
        $sebastian = User::factory()->create([
            'name' => 'SEBASTIAN ANDRES MATAMALA SOTO',
            'active' => true,
            'user_type' => 'staff',
        ]);

        Sanctum::actingAs($creator);

        $response = $this->getJson('/api/maintenance/work-orders/catalogs?include_dependencies=0')
            ->assertOk()
            ->assertJsonPath('current_user.id', $creator->id)
            ->assertJsonPath('current_user.name', 'Usuario creador');

        $catalog = collect($response->json('maintenance_assignees'));
        $catalogUser = $catalog->firstWhere('user_id', $sebastian->id);

        $this->assertNotNull($catalogUser);
        $this->assertSame($sebastian->id, $catalogUser['user_id']);
        $this->assertSame('SEBASTIAN ANDRES MATAMALA SOTO', $catalogUser['full_name']);
    }

    public function test_creator_is_the_assigner_and_assignees_are_saved_by_user_id(): void
    {
        $creator = $this->authorizedUser('Creador real de OT', ['crear_ot', 'editar_ot']);
        $sebastian = User::factory()->create([
            'name' => 'Sebastian Matamala',
            'active' => true,
            'user_type' => 'staff',
        ]);

        Sanctum::actingAs($creator);

        $response = $this->postJson('/api/maintenance/work-orders', [
            'requested_by' => 'Nombre enviado por el cliente',
            'sync_assignees' => true,
            'assigned_user_ids' => [$sebastian->id],
            'priority' => 'Media',
            'status' => 'Sin comenzar',
            'description' => 'Revisión de luminarias del acceso principal.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.requested_by', 'Creador real de OT')
            ->assertJsonPath('data.created_by_user_id', $creator->id)
            ->assertJsonPath('data.assignee_users.0.id', $sebastian->id);

        $workOrder = MaintenanceWorkOrder::query()->findOrFail($response->json('data.id'));

        $this->assertSame($creator->id, $workOrder->created_by_user_id);
        $this->assertSame('Creador real de OT', $workOrder->requested_by);
        $this->assertSame('Sebastian Matamala', $workOrder->assigned_to);
        $this->assertDatabaseHas('maintenance_work_order_assignees', [
            'maintenance_work_order_id' => $workOrder->id,
            'user_id' => $sebastian->id,
            'assignee_name_snapshot' => 'Sebastian Matamala',
        ]);

        $editor = $this->authorizedUser('Editor posterior', ['editar_ot']);
        Sanctum::actingAs($editor);

        $this->putJson("/api/maintenance/work-orders/{$workOrder->id}", [
            'requested_by' => 'Editor posterior',
            'priority' => 'Alta',
            'status' => 'En proceso',
            'description' => 'Revisión actualizada.',
        ])
            ->assertOk()
            ->assertJsonPath('data.requested_by', 'Creador real de OT')
            ->assertJsonPath('data.created_by_user_id', $creator->id);

        $workOrder->refresh();
        $this->assertSame($creator->id, $workOrder->created_by_user_id);
        $this->assertSame('Creador real de OT', $workOrder->requested_by);
        $this->assertTrue($workOrder->assigneeUsers()->whereKey($sebastian->id)->exists());
    }

    private function authorizedUser(string $name, array $permissionSlugs): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'active' => true,
            'user_type' => 'staff',
        ]);
        $role = Role::query()->create([
            'name' => "Rol {$name}",
            'slug' => 'maintenance-test-'.strtolower(str_replace(' ', '-', $name)),
            'active' => true,
        ]);

        foreach ($permissionSlugs as $slug) {
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
