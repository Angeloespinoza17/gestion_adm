<?php

namespace Tests\Feature\RiskPrevention;

use App\Models\Cargo;
use App\Models\RiskPrevention\RiskPreventionEppDelivery;
use App\Models\RiskPrevention\RiskPreventionEppDeliveryRecord;
use App\Models\RiskPrevention\RiskPreventionEppItem;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\Modules\PrevencionRiesgosModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiskPreventionEppDeliveryRecordTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PrevencionRiesgosModuleSeeder::class);
        $this->manager = User::query()->where('email', 'superadmin@cnscgestion.cl')->firstOrFail();
        Sanctum::actingAs($this->manager);
    }

    public function test_manager_can_bulk_load_the_epp_catalog_and_update_existing_rows(): void
    {
        $payload = [
            'items' => [
                [
                    'name' => 'Casco de seguridad',
                    'epp_type' => 'Protección de cabeza',
                    'stock' => 20,
                    'minimum_stock' => 5,
                    'unit' => 'unidad',
                    'description' => 'Casco certificado',
                    'active' => true,
                ],
                [
                    'name' => 'Guante anticorte',
                    'epp_type' => 'Protección de manos',
                    'stock' => 30,
                    'minimum_stock' => 8,
                    'unit' => 'par',
                    'active' => true,
                ],
            ],
        ];

        $this->postJson('/api/risk-prevention/epp/items/bulk', $payload)
            ->assertCreated()
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.updated', 0);

        $payload['items'][0]['stock'] = 14;

        $this->postJson('/api/risk-prevention/epp/items/bulk', $payload)
            ->assertCreated()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.updated', 2);

        $this->assertDatabaseCount('prevent_epp_items', 2);
        $this->assertDatabaseHas('prevent_epp_items', [
            'name' => 'Casco de seguridad',
            'stock' => 14,
        ]);
    }

    public function test_manager_can_register_multiple_items_in_one_fo_prev_03_act(): void
    {
        $cargo = Cargo::query()->create([
            'name' => 'Auxiliar de mantención',
            'slug' => 'auxiliar-mantencion-test',
            'active' => true,
        ]);
        $staff = Staff::query()->create([
            'full_name' => 'María Prevención',
            'rut' => '12.345.678-5',
            'cargo_id' => $cargo->id,
            'status' => 'activo',
            'active' => true,
        ]);
        $helmet = $this->createItem('Casco de seguridad', 'unidad', 10);
        $gloves = $this->createItem('Guantes anticorte', 'par', 8);

        $response = $this->postJson('/api/risk-prevention/epp/delivery-records', [
            'staff_id' => $staff->id,
            'employee_name' => 'Nombre alterado',
            'employee_rut' => 'RUT alterado',
            'employee_position' => 'Cargo alterado',
            'delivered_at' => '2026-07-25',
            'received_conformity' => true,
            'notes' => 'Entrega inicial',
            'items' => [
                [
                    'epp_item_id' => $helmet->id,
                    'quantity' => 2,
                    'replacement_due_at' => '2027-07-25',
                ],
                [
                    'epp_item_id' => $gloves->id,
                    'quantity' => 1,
                    'replacement_due_at' => null,
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.form_code', 'FO-PREV-03')
            ->assertJsonPath('data.form_revision', '01')
            ->assertJsonPath('data.employee_name_snapshot', 'María Prevención')
            ->assertJsonPath('data.employee_rut_snapshot', '12.345.678-5')
            ->assertJsonPath('data.employee_position_snapshot', 'Auxiliar de mantención')
            ->assertJsonPath('data.received_conformity', true)
            ->assertJsonCount(2, 'data.deliveries');

        $record = RiskPreventionEppDeliveryRecord::query()->firstOrFail();
        $this->assertSame('EPP-2026-000001', $record->folio);
        $this->assertNotNull($record->received_conformity_at);
        $this->assertSame(8, $helmet->fresh()->stock);
        $this->assertSame(7, $gloves->fresh()->stock);
        $this->assertDatabaseHas('prevent_epp_deliveries', [
            'delivery_record_id' => $record->id,
            'epp_item_id' => $helmet->id,
            'epp_name_snapshot' => 'Casco de seguridad',
            'unit_snapshot' => 'unidad',
            'quantity' => 2,
        ]);
    }

    public function test_insufficient_stock_rolls_back_the_complete_delivery(): void
    {
        $helmet = $this->createItem('Casco de seguridad', 'unidad', 1);

        $this->postJson('/api/risk-prevention/epp/delivery-records', [
            'employee_name' => 'Funcionario de prueba',
            'delivered_at' => '2026-07-25',
            'received_conformity' => false,
            'items' => [
                [
                    'epp_item_id' => $helmet->id,
                    'quantity' => 2,
                    'replacement_due_at' => null,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.quantity']);

        $this->assertDatabaseCount('prevent_epp_delivery_records', 0);
        $this->assertDatabaseCount('prevent_epp_deliveries', 0);
        $this->assertSame(1, $helmet->fresh()->stock);
    }

    public function test_delivery_record_index_returns_lines_and_operational_summary(): void
    {
        $item = $this->createItem('Lentes de seguridad', 'unidad', 5);
        $record = RiskPreventionEppDeliveryRecord::query()->create([
            'folio' => 'EPP-2026-000099',
            'form_code' => 'FO-PREV-03',
            'form_revision' => '01',
            'employee_name_snapshot' => 'Trabajador Ejemplo',
            'delivered_at' => '2026-07-25',
            'received_conformity' => false,
            'created_by' => $this->manager->id,
            'updated_by' => $this->manager->id,
        ]);
        RiskPreventionEppDelivery::query()->create([
            'delivery_record_id' => $record->id,
            'epp_item_id' => $item->id,
            'epp_name_snapshot' => $item->name,
            'unit_snapshot' => $item->unit,
            'employee_name' => 'Trabajador Ejemplo',
            'quantity' => 3,
            'delivered_at' => '2026-07-25',
            'status' => 'vigente',
        ]);

        $this->getJson('/api/risk-prevention/epp/delivery-records')
            ->assertOk()
            ->assertJsonPath('data.0.folio', 'EPP-2026-000099')
            ->assertJsonPath('data.0.total_units', 3)
            ->assertJsonPath('summary.total_records', 1)
            ->assertJsonPath('summary.pending_conformity', 1)
            ->assertJsonPath('summary.delivered_units', 3);
    }

    public function test_epp_with_delivery_history_cannot_be_deleted(): void
    {
        $item = $this->createItem('Zapato de seguridad', 'par', 4);
        RiskPreventionEppDelivery::query()->create([
            'epp_item_id' => $item->id,
            'employee_name' => 'Trabajador Ejemplo',
            'quantity' => 1,
            'delivered_at' => '2026-07-25',
            'status' => 'vigente',
        ]);

        $this->deleteJson("/api/risk-prevention/epp/items/{$item->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['epp_item']);

        $this->assertDatabaseHas('prevent_epp_items', ['id' => $item->id]);
    }

    private function createItem(string $name, string $unit, int $stock): RiskPreventionEppItem
    {
        return RiskPreventionEppItem::query()->create([
            'name' => $name,
            'epp_type' => 'Protección personal',
            'stock' => $stock,
            'minimum_stock' => 1,
            'unit' => $unit,
            'active' => true,
            'created_by' => $this->manager->id,
            'updated_by' => $this->manager->id,
        ]);
    }
}
