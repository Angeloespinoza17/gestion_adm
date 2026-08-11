<?php

namespace Tests\Feature\Maintenance;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\MaintenanceDependency;
use App\Models\MaintenanceWorkOrder;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaintenanceWorkloadDependencyFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_dependency_filter_uses_current_inventory_location_and_preserves_general_work_orders(): void
    {
        $originalLocation = $this->dependency('DEP-ORIG', 'Ubicación registrada en OT');
        $currentLocation = $this->dependency('DEP-ACT', 'Ubicación actual del bien');

        $category = InventoryCategory::query()->create([
            'name' => 'Equipamiento de prueba',
            'slug' => 'equipamiento-prueba',
            'code_prefix' => 'EQP',
            'active' => true,
        ]);
        $item = InventoryItem::query()->create([
            'code' => 'INV-UBI-001',
            'name' => 'Bien trasladado',
            'category_id' => $category->id,
            'dependency_id' => $currentLocation->id,
            'status' => 'Activo',
            'condition' => 'Bueno',
            'item_type' => 'asset',
            'active' => true,
        ]);

        $assignee = Staff::query()->create([
            'full_name' => 'Responsable ubicación',
            'rut' => '19.111.222-3',
            'status' => 'activo',
            'active' => true,
            'can_receive_maintenance_orders' => true,
            'maintenance_role' => 'auxiliar_mantenimiento',
        ]);

        $inventoryOrder = MaintenanceWorkOrder::query()->create([
            'maintenance_dependency_id' => $originalLocation->id,
            'inventory_item_id' => $item->id,
            'assigned_to' => $assignee->full_name,
            'priority' => 'Alta',
            'status' => 'En proceso',
            'description' => 'OT asociada al bien trasladado.',
        ]);
        $generalOrder = MaintenanceWorkOrder::query()->create([
            'maintenance_dependency_id' => $originalLocation->id,
            'assigned_to' => $assignee->full_name,
            'priority' => 'Media',
            'status' => 'Sin comenzar',
            'description' => 'OT general de la dependencia original.',
        ]);

        Sanctum::actingAs($this->authorizedUser());
        $countsBefore = [MaintenanceWorkOrder::count(), InventoryItem::count()];

        $currentResponse = $this->getJson(
            '/api/maintenance/work-orders/workload?dependency_id='.$currentLocation->id
        );
        $currentRow = collect($currentResponse->json('rows'))->firstWhere('assignee', $assignee->full_name);

        $currentResponse->assertOk();
        $this->assertSame(1, $currentRow['assigned']);

        $originalResponse = $this->getJson(
            '/api/maintenance/work-orders/workload?dependency_id='.$originalLocation->id
        );
        $originalRow = collect($originalResponse->json('rows'))->firstWhere('assignee', $assignee->full_name);

        $originalResponse->assertOk();
        $this->assertSame(1, $originalRow['assigned']);

        $this->getJson(
            '/api/maintenance/work-orders/assignee-report?assignee='.
            urlencode($assignee->full_name).'&dependency_id='.$currentLocation->id
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inventoryOrder->id);

        $this->getJson(
            '/api/maintenance/work-orders/assignee-report?assignee='.
            urlencode($assignee->full_name).'&dependency_id='.$originalLocation->id
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $generalOrder->id);

        $this->assertSame($countsBefore, [MaintenanceWorkOrder::count(), InventoryItem::count()]);
    }

    private function dependency(string $code, string $name): MaintenanceDependency
    {
        return MaintenanceDependency::query()->create([
            'dependency_kind' => MaintenanceDependency::KIND_SPACE,
            'code' => $code,
            'name' => $name,
            'active' => true,
            'is_maintenance_location' => true,
            'is_inventory_auditable' => true,
        ]);
    }

    private function authorizedUser(): User
    {
        $user = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $role = Role::query()->create([
            'name' => 'Carga por dependencia',
            'slug' => 'carga-por-dependencia',
            'active' => true,
        ]);

        foreach (['ver_reportes_mantencion', 'exportar_mantencion'] as $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'active' => true]
            );
            $role->permissions()->attach($permission);
        }

        $user->roles()->attach($role);

        return $user;
    }
}
