<?php

namespace Tests\Feature\Supply;

use App\Models\Role;
use App\Models\Supply\SupplyRequest;
use App\Models\Supply\SupplyRequestItem;
use App\Models\User;
use Database\Seeders\Modules\SupplyModuleSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplyRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        $this->seed(SupplyModuleSeeder::class);

        $this->manager = User::factory()->create(['name' => 'Encargada de Abastecimiento', 'active' => true]);
        $this->manager->roles()->attach(Role::query()->where('slug', 'encargado_mantencion')->firstOrFail());
        $this->superadmin = User::factory()->create(['name' => 'Super Admin Revisor', 'active' => true]);
        $this->superadmin->roles()->attach(Role::query()->where('slug', 'super_admin')->firstOrFail());
    }

    public function test_manager_can_request_inventory_and_custom_cleaning_products_with_private_photo(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->manager);
        $catalogItem = $this->createSupply('cleaning', 'paper', 'Papel higiénico', 'rollo');

        $response = $this->post('/api/supplies/requests', [
            'title' => 'Reposición mensual de aseo',
            'destination' => 'Bodega central',
            'needed_by' => '2026-09-12',
            'notes' => 'Priorizar productos biodegradables.',
            'items' => [
                [
                    'supply_item_id' => $catalogItem['id'],
                    'requested_quantity' => 24,
                ],
                [
                    'name' => 'Cepillo para juntas',
                    'description' => 'Cerdas rígidas',
                    'unit' => 'unidad',
                    'requested_quantity' => 6,
                    'photo' => UploadedFile::fake()->image('cepillo.jpg', 700, 500),
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.folio', 'SOL-ABA-2026-000001')
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.items.0.item_name_snapshot', 'Papel higiénico')
            ->assertJsonPath('data.items.0.requested_quantity', '24.00')
            ->assertJsonPath('data.items.0.final_quantity', '24.00')
            ->assertJsonPath('data.items.1.item_name_snapshot', 'Cepillo para juntas');

        $customLine = SupplyRequestItem::query()->whereNull('supply_item_id')->firstOrFail();
        Storage::disk('local')->assertExists($customLine->reference_photo_path);
        $this->get("/api/supplies/request-items/{$customLine->id}/photo")->assertOk();
        $this->assertDatabaseHas('supply_request_status_logs', [
            'supply_request_id' => $customLine->supply_request_id,
            'from_status' => null,
            'to_status' => 'submitted',
            'changed_by' => $this->manager->id,
        ]);
    }

    public function test_superadmin_can_edit_final_list_preserve_original_quantity_and_emit_quote_status(): void
    {
        Sanctum::actingAs($this->manager);
        $catalogItem = $this->createSupply('cleaning', 'soap', 'Jabón líquido', 'litro');
        $requestId = $this->postJson('/api/supplies/requests', [
            'title' => 'Solicitud semanal',
            'items' => [[
                'supply_item_id' => $catalogItem['id'],
                'requested_quantity' => 8,
            ]],
        ])->assertCreated()->json('data.id');
        $originalLine = SupplyRequestItem::query()->where('supply_request_id', $requestId)->firstOrFail();

        Sanctum::actingAs($this->superadmin);
        $response = $this->postJson("/api/superadmin/supply-requests/{$requestId}", [
            'title' => 'Solicitud semanal revisada',
            'destination' => 'Bodega de auxiliares',
            'needed_by' => '2026-09-09',
            'status' => 'quoted',
            'review_notes' => 'Cotizar envases sellados.',
            'items' => [
                [
                    'id' => $originalLine->id,
                    'supply_item_id' => $catalogItem['id'],
                    'name' => 'Jabón líquido institucional',
                    'description' => 'Envase de cinco litros',
                    'unit' => 'litro',
                    'requested_quantity' => 99,
                    'final_quantity' => 12,
                ],
                [
                    'name' => 'Dispensador mural',
                    'description' => 'Color blanco',
                    'unit' => 'unidad',
                    'requested_quantity' => 3,
                    'final_quantity' => 4,
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'quoted')
            ->assertJsonPath('data.status_label', 'Cotización emitida')
            ->assertJsonPath('data.reviewer.name', 'Super Admin Revisor')
            ->assertJsonPath('data.items.0.requested_quantity', '8.00')
            ->assertJsonPath('data.items.0.final_quantity', '12.00')
            ->assertJsonPath('data.items.1.final_quantity', '4.00');

        $request = SupplyRequest::query()->findOrFail($requestId);
        $this->assertNotNull($request->quoted_at);
        $this->assertDatabaseHas('supply_request_status_logs', [
            'supply_request_id' => $requestId,
            'from_status' => 'submitted',
            'to_status' => 'quoted',
            'changed_by' => $this->superadmin->id,
        ]);
    }

    public function test_non_superadmin_cannot_use_exclusive_review_endpoints(): void
    {
        Sanctum::actingAs($this->manager);
        $this->getJson('/api/superadmin/supply-requests')->assertForbidden();
    }

    public function test_request_permissions_and_navigation_are_assigned_without_exposing_superadmin_child(): void
    {
        $managerRole = Role::query()->where('slug', 'encargado_mantencion')->with(['permissions:id,slug', 'modules:id,slug'])->firstOrFail();
        $superRole = Role::query()->where('slug', 'super_admin')->with('modules:id,slug')->firstOrFail();

        $this->assertEmpty(array_diff([
            'ver_solicitudes_abastecimiento', 'crear_solicitudes_abastecimiento',
        ], $managerRole->permissions->pluck('slug')->all()));
        $this->assertContains('supplies_requests', $managerRole->modules->pluck('slug')->all());
        $this->assertNotContains('superadmin_supply_requests', $managerRole->modules->pluck('slug')->all());
        $this->assertContains('superadmin_supply_requests', $superRole->modules->pluck('slug')->all());
    }

    private function createSupply(string $section, string $type, string $name, string $unit): array
    {
        return $this->postJson('/api/supplies/items', [
            'section' => $section,
            'supply_type' => $type,
            'name' => $name,
            'unit_of_measure' => $unit,
            'minimum_stock' => 5,
        ])->assertCreated()->json('data');
    }
}
