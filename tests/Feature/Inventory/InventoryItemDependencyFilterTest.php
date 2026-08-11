<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\MaintenanceDependency;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryItemDependencyFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_items_can_be_filtered_and_searched_by_their_dependency_without_mutating_inventory(): void
    {
        $firstDependency = $this->dependency('DEP-INV-01', 'Bodega norte', 'Primer piso');
        $secondDependency = $this->dependency('DEP-INV-02', 'Sala multiuso', 'Segundo piso');
        $emptyDependency = $this->dependency('DEP-INV-03', 'Sala sin bienes', 'Tercer piso');
        $category = InventoryCategory::query()->create([
            'name' => 'Mobiliario de prueba',
            'slug' => 'mobiliario-prueba',
            'code_prefix' => 'MOB',
            'active' => true,
        ]);

        $firstItem = $this->item($category, $firstDependency, 'INV-DEP-001', 'Mesa de reuniones');
        $secondItem = $this->item($category, $secondDependency, 'INV-DEP-002', 'Silla apilable');
        $this->item($category, null, 'INV-DEP-003', 'Bien sin ubicación');
        $anotherFirstItem = $this->item($category, $firstDependency, 'INV-DEP-004', 'Pizarra móvil');

        Sanctum::actingAs($this->authorizedUser());
        $countBefore = InventoryItem::query()->count();

        $this->getJson('/api/inventory/items?dependency_id='.$firstDependency->id)
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonFragment(['id' => $firstItem->id])
            ->assertJsonFragment(['id' => $anotherFirstItem->id])
            ->assertJsonFragment(['dependency_id' => $firstDependency->id]);

        $this->getJson('/api/inventory/items?search='.urlencode('Segundo piso'))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $secondItem->id);

        $catalogResponse = $this->getJson('/api/inventory/items/catalogs')
            ->assertOk()
            ->assertJsonPath('dependencies.0.id', $firstDependency->id)
            ->assertJsonPath('dependencies.0.inventory_items_count', 2)
            ->assertJsonPath('dependencies.1.id', $secondDependency->id)
            ->assertJsonPath('dependencies.1.inventory_items_count', 1)
            ->assertJsonFragment([
                'id' => $firstDependency->id,
                'code' => 'DEP-INV-01',
                'name' => 'Bodega norte',
            ]);

        $dependencies = collect($catalogResponse->json('dependencies'));
        $emptyDependencyPayload = $dependencies->firstWhere('id', $emptyDependency->id);
        $counts = $dependencies->pluck('inventory_items_count')->map(fn ($count) => (int) $count);

        $this->assertIsArray($emptyDependencyPayload);
        $this->assertSame(0, $emptyDependencyPayload['inventory_items_count']);
        $this->assertSame($counts->sortDesc()->values()->all(), $counts->all());

        $this->assertSame($countBefore, InventoryItem::query()->count());
    }

    private function dependency(string $code, string $name, string $distribution): MaintenanceDependency
    {
        return MaintenanceDependency::query()->create([
            'dependency_kind' => MaintenanceDependency::KIND_SPACE,
            'code' => $code,
            'name' => $name,
            'distribution' => $distribution,
            'active' => true,
            'is_maintenance_location' => true,
            'is_inventory_auditable' => true,
        ]);
    }

    private function item(
        InventoryCategory $category,
        ?MaintenanceDependency $dependency,
        string $code,
        string $name
    ): InventoryItem {
        return InventoryItem::query()->create([
            'code' => $code,
            'name' => $name,
            'category_id' => $category->id,
            'dependency_id' => $dependency?->id,
            'status' => 'Activo',
            'condition' => 'Bueno',
            'item_type' => 'asset',
            'active' => true,
        ]);
    }

    private function authorizedUser(): User
    {
        $user = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $role = Role::query()->create([
            'name' => 'Consulta inventario por dependencia',
            'slug' => 'consulta-inventario-dependencia',
            'active' => true,
        ]);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'ver_inventario'],
            ['name' => 'Ver inventario', 'active' => true]
        );
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        return $user;
    }
}
