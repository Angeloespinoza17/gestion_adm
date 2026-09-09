<?php

namespace Tests\Feature\Supply;

use App\Models\Cargo;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Supply\SupplyDelivery;
use App\Models\Supply\SupplyItem;
use App\Models\Supply\SupplyStoreroom;
use App\Models\User;
use Database\Seeders\Modules\SupplyModuleSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplyManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Staff $recipient;

    private SupplyStoreroom $storeroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(SupplyModuleSeeder::class);

        $cargo = Cargo::query()->create([
            'name' => 'Auxiliar de aseo',
            'slug' => 'auxiliar-aseo-prueba',
            'active' => true,
        ]);
        $this->recipient = Staff::query()->create([
            'full_name' => 'María Auxiliar',
            'rut' => '12.345.678-5',
            'cargo_id' => $cargo->id,
            'status' => 'activo',
            'active' => true,
            'can_receive_maintenance_orders' => true,
            'maintenance_role' => 'auxiliar_aseo',
        ]);
        $this->manager = User::factory()->create([
            'name' => 'Cuenta de abastecimiento',
            'active' => true,
            'user_type' => 'staff',
        ]);
        $this->manager->roles()->attach(Role::query()->where('slug', 'encargado_mantencion')->firstOrFail());
        $this->storeroom = SupplyStoreroom::query()->where('code', 'PANOL-CENTRAL')->firstOrFail();
        Sanctum::actingAs($this->manager);
    }

    public function test_manager_can_create_a_cleaning_supply_with_a_private_reference_photo(): void
    {
        Storage::fake('local');

        $response = $this->post('/api/supplies/items', [
            'section' => 'cleaning',
            'supply_type' => 'cleaner',
            'name' => 'Desinfectante concentrado',
            'description' => 'Bidón de cinco litros',
            'unit_of_measure' => 'litro',
            'minimum_stock' => 8,
            'photo' => UploadedFile::fake()->image('desinfectante.jpg', 800, 800),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.section', 'cleaning')
            ->assertJsonPath('data.inventory_item.item_type', 'consumable')
            ->assertJsonPath('data.inventory_item.stock_quantity', '0.00')
            ->assertJsonMissingPath('data.reference_photo_path');

        $item = SupplyItem::query()->with('inventoryItem')->firstOrFail();
        $this->assertNotNull($item->reference_photo_path);
        Storage::disk('local')->assertExists($item->reference_photo_path);
        $this->get("/api/supplies/items/{$item->id}/photo")->assertOk();
        $this->assertSame('Insumos de aseo', $item->inventoryItem->category->name);
    }

    public function test_manager_can_replace_a_private_reference_photo_without_leaving_the_previous_file(): void
    {
        Storage::fake('local');

        $item = $this->createSupply('cleaning', 'cleaner', 'Limpiador con fotografía', 'litro', 2);

        $firstResponse = $this->post("/api/supplies/items/{$item->id}/photo", [
            'photo' => UploadedFile::fake()->image('foto-inicial.jpg', 640, 480),
        ]);
        $firstResponse
            ->assertOk()
            ->assertJsonPath('message', 'Foto de referencia actualizada.')
            ->assertJsonMissingPath('data.reference_photo_path');

        $firstPath = $item->fresh()->reference_photo_path;
        $this->assertNotNull($firstPath);
        Storage::disk('local')->assertExists($firstPath);

        $secondResponse = $this->post("/api/supplies/items/{$item->id}/photo", [
            'photo' => UploadedFile::fake()->image('foto-actualizada.png', 800, 600),
        ]);
        $secondResponse
            ->assertOk()
            ->assertJsonPath('message', 'Foto de referencia actualizada.')
            ->assertJsonMissingPath('data.reference_photo_path');

        $updatedPath = $item->fresh()->reference_photo_path;
        $this->assertNotSame($firstPath, $updatedPath);
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($updatedPath);

        $this->get("/api/supplies/items/{$item->id}/photo")
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_manager_can_view_and_edit_a_cleaning_supply(): void
    {
        $item = $this->createSupply('cleaning', 'soap', 'Jabón líquido original', 'litro', 4);

        $this->getJson("/api/supplies/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('data.inventory_item.name', 'Jabón líquido original')
            ->assertJsonPath('data.receipt_items_count', 0)
            ->assertJsonPath('data.delivery_items_count', 0)
            ->assertJsonPath('data.request_items_count', 0);

        $this->putJson("/api/supplies/items/{$item->id}", [
            'supply_type' => 'cleaner',
            'name' => 'Jabón líquido actualizado',
            'description' => 'Formato institucional',
            'unit_of_measure' => 'bidon',
            'minimum_stock' => 7,
            'supplier_id' => null,
            'active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.supply_type', 'cleaner')
            ->assertJsonPath('data.inventory_item.name', 'Jabón líquido actualizado')
            ->assertJsonPath('data.inventory_item.active', false);

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->inventory_item_id,
            'name' => 'Jabón líquido actualizado',
            'unit_of_measure' => 'bidon',
        ]);
    }

    public function test_manager_archives_an_unused_cleaning_supply_and_preserves_its_private_photo(): void
    {
        Storage::fake('local');

        $response = $this->post('/api/supplies/items', [
            'section' => 'cleaning',
            'supply_type' => 'paper',
            'name' => 'Papel absorbente sin uso',
            'unit_of_measure' => 'rollo',
            'photo' => UploadedFile::fake()->image('papel.jpg'),
        ])->assertCreated();

        $item = SupplyItem::query()->with('inventoryItem')->findOrFail($response->json('data.id'));
        $inventoryItemId = $item->inventory_item_id;
        $photoPath = $item->reference_photo_path;

        $this->deleteJson("/api/supplies/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('archived', true)
            ->assertJsonPath('message', 'Producto eliminado del registro. Su ficha, fotografía, existencias y movimientos se conservaron para trazabilidad.');

        $this->assertSoftDeleted('supply_items', ['id' => $item->id]);
        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventoryItemId,
            'active' => false,
        ]);
        Storage::disk('local')->assertExists($photoPath);
    }

    public function test_manager_can_remove_a_cleaning_supply_with_stock_without_losing_history(): void
    {
        $item = $this->createSupply('cleaning', 'paper', 'Papel con trazabilidad', 'rollo', 10);
        $this->receive($item, 20);

        $this->deleteJson("/api/supplies/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('archived', true)
            ->assertJsonPath('message', 'Producto eliminado del registro. Su ficha, fotografía, existencias y movimientos se conservaron para trazabilidad.');

        $this->assertSoftDeleted('supply_items', ['id' => $item->id]);
        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->inventory_item_id,
            'active' => false,
            'stock_quantity' => 20,
        ]);
        $this->assertDatabaseHas('supply_receipt_items', ['supply_item_id' => $item->id]);
        $this->assertDatabaseHas('inventory_stock_movements', ['inventory_item_id' => $item->inventory_item_id]);
        $this->assertSame('20.00', $item->inventoryItem->fresh()->stock_quantity);

        $this->getJson('/api/supplies/items?section=cleaning')
            ->assertOk()
            ->assertJsonMissing(['id' => $item->id]);

        $this->getJson('/api/supplies/receipts?section=cleaning')
            ->assertOk()
            ->assertJsonPath('data.0.items.0.supply_item.inventory_item.name', 'Papel con trazabilidad')
            ->assertJsonPath('data.0.items.0.supply_item.inventory_item.stock_quantity', '20.00');

        $this->getJson("/api/supplies/items/{$item->id}")->assertNotFound();
    }

    public function test_read_only_user_can_view_but_cannot_edit_or_delete_a_cleaning_supply(): void
    {
        $item = $this->createSupply('cleaning', 'implement', 'Escobillón institucional', 'unidad', 2);
        $viewerRole = Role::query()->create([
            'name' => 'Consulta abastecimiento prueba',
            'slug' => 'consulta-abastecimiento-prueba',
            'active' => true,
        ]);
        $viewerRole->permissions()->attach(
            Permission::query()->where('slug', 'ver_abastecimiento')->firstOrFail(),
        );
        $viewer = User::factory()->create(['active' => true]);
        $viewer->roles()->attach($viewerRole);
        Sanctum::actingAs($viewer);

        $this->getJson("/api/supplies/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('data.inventory_item.name', 'Escobillón institucional');

        $this->putJson("/api/supplies/items/{$item->id}", [
            'supply_type' => 'implement',
            'name' => 'Cambio no autorizado',
            'unit_of_measure' => 'unidad',
            'active' => true,
        ])->assertForbidden();
        $this->deleteJson("/api/supplies/items/{$item->id}")->assertForbidden();

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->inventory_item_id,
            'name' => 'Escobillón institucional',
        ]);
    }

    public function test_delivery_product_search_only_returns_active_items_with_available_stock(): void
    {
        $available = $this->createSupply('cleaning', 'cleaner', 'Buscador disponible', 'litro', 2);
        $inactive = $this->createSupply('cleaning', 'cleaner', 'Buscador inactivo', 'litro', 2);
        $empty = $this->createSupply('cleaning', 'cleaner', 'Buscador sin stock', 'litro', 2);

        $this->receive($available, 12);
        $this->receive($inactive, 8);
        $inactive->inventoryItem()->update(['active' => false]);

        $this->getJson('/api/supplies/items?section=cleaning&search=Buscador&stock_status=available&active_only=1&per_page=20')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $available->id)
            ->assertJsonPath('data.0.inventory_item.name', 'Buscador disponible')
            ->assertJsonMissing(['id' => $inactive->id])
            ->assertJsonMissing(['id' => $empty->id]);
    }

    public function test_manager_can_register_and_move_maintenance_storeroom_tools(): void
    {
        $response = $this->postJson('/api/supplies/items', [
            'section' => SupplyItem::SECTION_MAINTENANCE_STOREROOM,
            'storeroom_id' => $this->storeroom->id,
            'supply_type' => 'hand_tool',
            'name' => 'Llave ajustable 12 pulgadas',
            'description' => 'Herramienta manual del pañol central',
            'unit_of_measure' => 'unidad',
            'minimum_stock' => 2,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.section', SupplyItem::SECTION_MAINTENANCE_STOREROOM)
            ->assertJsonPath('data.supply_type', 'hand_tool')
            ->assertJsonPath('data.inventory_item.stock_quantity', '0.00');

        $tool = SupplyItem::query()->with('inventoryItem.category')->findOrFail($response->json('data.id'));
        $this->assertSame('Pañol de mantenimiento', $tool->inventoryItem->category->name);

        $this->receive($tool, 6);
        $this->postJson('/api/supplies/deliveries', [
            'section' => SupplyItem::SECTION_MAINTENANCE_STOREROOM,
            'storeroom_id' => $this->storeroom->id,
            'delivered_at' => '2026-09-01',
            'recipient_staff_id' => $this->recipient->id,
            'destination' => 'Taller de mantenimiento',
            'items' => [
                ['supply_item_id' => $tool->id, 'quantity' => 1, 'notes' => 'Asignación operativa'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.section', SupplyItem::SECTION_MAINTENANCE_STOREROOM)
            ->assertJsonPath('data.items.0.new_stock', '5.00');

        $this->getJson('/api/supplies/items?section=maintenance_storeroom&storeroom_id='.$this->storeroom->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.inventory_item.name', 'Llave ajustable 12 pulgadas');
    }

    public function test_manager_can_search_and_attach_existing_inventory_items_to_a_specific_storeroom(): void
    {
        $category = InventoryCategory::query()->create([
            'name' => 'Herramientas generales',
            'slug' => 'herramientas-generales-prueba',
            'code_prefix' => 'HTEST',
            'active' => true,
        ]);
        $drill = InventoryItem::query()->create([
            'code' => 'INV-TEST-TALADRO',
            'name' => 'Taladro de banco',
            'description' => 'Activo ya registrado en Inventario',
            'category_id' => $category->id,
            'brand' => 'Makita',
            'status' => 'En bodega',
            'condition' => 'Bueno',
            'item_type' => 'asset',
            'unit_of_measure' => 'unidad',
            'active' => true,
        ]);
        $screws = InventoryItem::query()->create([
            'code' => 'INV-TEST-TORNILLOS',
            'name' => 'Caja de tornillos',
            'category_id' => $category->id,
            'status' => 'En bodega',
            'condition' => 'Bueno',
            'item_type' => 'consumable',
            'stock_quantity' => 120,
            'unit_of_measure' => 'unidad',
            'active' => true,
        ]);

        $warehouseResponse = $this->postJson('/api/supplies/storerooms', [
            'name' => 'Pañol taller norte',
            'description' => 'Bodega de apoyo para mantención exterior',
        ])->assertCreated();
        $warehouseId = $warehouseResponse->json('data.id');

        $candidateResponse = $this->getJson('/api/supplies/storerooms/inventory-candidates?search=Taladro')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $drill->id)
            ->assertJsonPath('data.0.name', 'Taladro de banco');
        $this->assertSame('asset', $candidateResponse->json('data.0.item_type'));

        $this->postJson("/api/supplies/storerooms/{$warehouseId}/items", [
            'inventory_item_ids' => [$drill->id, $screws->id],
        ])
            ->assertCreated()
            ->assertJsonPath('attached_count', 2);

        $this->assertDatabaseCount('inventory_items', 2);
        $this->assertDatabaseHas('supply_items', [
            'inventory_item_id' => $drill->id,
            'section' => SupplyItem::SECTION_MAINTENANCE_STOREROOM,
            'storeroom_id' => $warehouseId,
        ]);
        $this->assertDatabaseHas('supply_items', [
            'inventory_item_id' => $screws->id,
            'storeroom_id' => $warehouseId,
        ]);

        $this->getJson("/api/supplies/items?section=maintenance_storeroom&storeroom_id={$warehouseId}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('summary.total_items', 2);
        $this->getJson('/api/supplies/items?section=maintenance_storeroom&storeroom_id='.$this->storeroom->id)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/supplies/storerooms/inventory-candidates?search=Taladro')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
        $this->postJson("/api/supplies/storerooms/{$this->storeroom->id}/items", [
            'inventory_item_ids' => [$drill->id],
        ])->assertUnprocessable()->assertJsonValidationErrors(['inventory_item_ids']);
    }

    public function test_purchase_and_delivery_update_the_central_stock_with_auditable_movements(): void
    {
        $item = $this->createSupply('cleaning', 'paper', 'Papel higiénico', 'rollo', 10);

        $this->postJson('/api/supplies/receipts', [
            'section' => 'cleaning',
            'purchased_at' => '2026-08-29',
            'document_type' => 'Factura',
            'document_number' => 'F-1098',
            'total_amount' => 45000,
            'items' => [
                ['supply_item_id' => $item->id, 'quantity' => 40, 'unit_cost' => 1125],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.folio', 'ABA-ING-2026-000001')
            ->assertJsonPath('data.items.0.previous_stock', '0.00')
            ->assertJsonPath('data.items.0.new_stock', '40.00');

        $deliveryResponse = $this->postJson('/api/supplies/deliveries', [
            'section' => 'cleaning',
            'delivered_at' => '2026-08-31',
            'recipient_staff_id' => $this->recipient->id,
            'destination' => 'Edificio principal',
            'items' => [
                ['supply_item_id' => $item->id, 'quantity' => 12, 'notes' => 'Reposición semanal'],
            ],
        ]);

        $deliveryResponse
            ->assertCreated()
            ->assertJsonPath('data.folio', 'ABA-ENT-2026-000001')
            ->assertJsonPath('data.items.0.item_name_snapshot', 'Papel higiénico')
            ->assertJsonPath('data.items.0.previous_stock', '40.00')
            ->assertJsonPath('data.items.0.new_stock', '28.00')
            ->assertJsonPath('data.recipient_staff_id', $this->recipient->id)
            ->assertJsonPath('data.recipient_name', 'María Auxiliar')
            ->assertJsonPath('data.recipient_rut', '12.345.678-5')
            ->assertJsonPath('data.recipient_role', 'Auxiliar de aseo');

        $this->assertSame('28.00', $item->inventoryItem->fresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_stock_movements', 2);
        $this->assertSame(['in', 'out'], InventoryStockMovement::query()->orderBy('id')->pluck('movement_type')->all());

        $delivery = SupplyDelivery::query()->firstOrFail();
        $this->getJson("/api/supplies/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertJsonPath('data.recipient_name', 'María Auxiliar')
            ->assertJsonPath('data.items.0.quantity', '12.00');
    }

    public function test_insufficient_stock_rolls_back_the_complete_delivery(): void
    {
        $pellet = $this->createSupply('heating', 'pellet', 'Pellet certificado', 'saco', 5);
        $firewood = $this->createSupply('heating', 'firewood', 'Leña seca', 'metro_cubico', 2);
        $this->receive($pellet, 10);
        $this->receive($firewood, 1.5);

        $this->postJson('/api/supplies/deliveries', [
            'section' => 'heating',
            'delivered_at' => '2026-08-31',
            'recipient_staff_id' => $this->recipient->id,
            'items' => [
                ['supply_item_id' => $pellet->id, 'quantity' => 3],
                ['supply_item_id' => $firewood->id, 'quantity' => 2],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.1.quantity']);

        $this->assertDatabaseCount('supply_deliveries', 0);
        $this->assertSame('10.00', $pellet->inventoryItem->fresh()->stock_quantity);
        $this->assertSame('1.50', $firewood->inventoryItem->fresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_stock_movements', 2);
    }

    public function test_delivery_catalog_only_lists_active_staff_marked_to_receive_maintenance_orders(): void
    {
        $ineligibleStaff = Staff::query()->create([
            'full_name' => 'Funcionario que no recibe OT',
            'rut' => '9.876.543-3',
            'status' => 'activo',
            'active' => true,
            'can_receive_maintenance_orders' => false,
        ]);
        $inactiveStaff = Staff::query()->create([
            'full_name' => 'Funcionario inactivo que recibe OT',
            'rut' => '8.765.432-2',
            'status' => 'inactivo',
            'active' => false,
            'can_receive_maintenance_orders' => true,
            'maintenance_role' => 'apoyo_operativo',
        ]);

        $response = $this->getJson('/api/supplies/catalogs')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $this->recipient->id,
                'name' => 'María Auxiliar',
                'rut' => '12.345.678-5',
                'role' => 'Auxiliar de aseo',
            ]);

        $recipientIds = collect($response->json('delivery_recipients'))->pluck('id')->all();
        $this->assertContains($this->recipient->id, $recipientIds);
        $this->assertNotContains($ineligibleStaff->id, $recipientIds);
        $this->assertNotContains($inactiveStaff->id, $recipientIds);
        $this->assertNull($this->recipient->user, 'El personal receptor no debe necesitar una cuenta ni permiso para crear OT.');
    }

    public function test_delivery_rejects_staff_not_marked_to_receive_maintenance_orders(): void
    {
        $item = $this->createSupply('cleaning', 'soap', 'Jabón líquido', 'litro', 5);
        $this->receive($item, 20);
        $ineligibleStaff = Staff::query()->create([
            'full_name' => 'Funcionario no receptor',
            'rut' => '7.654.321-1',
            'status' => 'activo',
            'active' => true,
            'can_receive_maintenance_orders' => false,
        ]);

        $this->postJson('/api/supplies/deliveries', [
            'section' => 'cleaning',
            'delivered_at' => '2026-09-01',
            'recipient_staff_id' => $ineligibleStaff->id,
            'items' => [
                ['supply_item_id' => $item->id, 'quantity' => 2],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient_staff_id']);

        $this->assertDatabaseCount('supply_deliveries', 0);
        $this->assertSame('20.00', $item->inventoryItem->fresh()->stock_quantity);
    }

    public function test_supply_permissions_and_navigation_children_are_assigned_to_maintenance_manager(): void
    {
        $role = Role::query()->where('slug', 'encargado_mantencion')->with(['permissions:id,slug', 'modules:id,slug'])->firstOrFail();

        $this->assertEmpty(array_diff([
            'ver_abastecimiento',
            'gestionar_insumos_abastecimiento',
            'registrar_compras_abastecimiento',
            'registrar_entregas_abastecimiento',
            'exportar_actas_abastecimiento',
        ], $role->permissions->pluck('slug')->all()));
        $this->assertEmpty(array_diff([
            'supplies', 'supplies_cleaning', 'supplies_heating', 'supplies_maintenance_storeroom',
        ], $role->modules->pluck('slug')->all()));
    }

    private function createSupply(string $section, string $type, string $name, string $unit, float $minimum): SupplyItem
    {
        $response = $this->postJson('/api/supplies/items', [
            'section' => $section,
            'storeroom_id' => $section === SupplyItem::SECTION_MAINTENANCE_STOREROOM ? $this->storeroom->id : null,
            'supply_type' => $type,
            'name' => $name,
            'unit_of_measure' => $unit,
            'minimum_stock' => $minimum,
        ])->assertCreated();

        return SupplyItem::query()->with('inventoryItem')->findOrFail($response->json('data.id'));
    }

    private function receive(SupplyItem $item, float $quantity): void
    {
        $this->postJson('/api/supplies/receipts', [
            'section' => $item->section,
            'storeroom_id' => $item->section === SupplyItem::SECTION_MAINTENANCE_STOREROOM ? $item->storeroom_id : null,
            'purchased_at' => '2026-08-30',
            'items' => [['supply_item_id' => $item->id, 'quantity' => $quantity]],
        ])->assertCreated();
    }
}
