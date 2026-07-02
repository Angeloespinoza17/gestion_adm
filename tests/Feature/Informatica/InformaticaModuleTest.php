<?php

namespace Tests\Feature\Informatica;

use App\Models\It\ItEquipment;
use App\Models\It\ItEquipmentLoan;
use App\Models\It\ItEquipmentMaintenanceReport;
use App\Models\Permission;
use App\Models\SystemModule;
use App\Models\User;
use Database\Seeders\InformaticaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InformaticaModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_informatica_seeder_creates_core_records(): void
    {
        $this->seed(InformaticaSeeder::class);

        $this->assertGreaterThan(0, ItEquipment::query()->count());
        $this->assertGreaterThan(0, ItEquipmentLoan::query()->count());
        $this->assertGreaterThan(0, ItEquipmentMaintenanceReport::query()->count());
        $this->assertNotNull(SystemModule::query()->firstWhere('slug', 'informatica'));
        $this->assertNotNull(Permission::query()->firstWhere('slug', 'informatica.ver'));
    }

    public function test_super_admin_can_open_informatica_dashboard_api(): void
    {
        $this->seedAndActAsSuperAdmin();

        $response = $this->getJson('/api/informatica/dashboard');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'metrics',
                'charts',
                'recent',
            ]);
    }

    public function test_can_create_equipment(): void
    {
        $this->seedAndActAsSuperAdmin();

        $response = $this->postJson('/api/informatica/equipos', [
            'internal_code' => 'INF-IT-TEST-9001',
            'equipment_type' => 'notebook',
            'brand' => 'Asus',
            'model' => 'ExpertBook B5',
            'serial_number' => 'INF-IT-TEST-SN-9001',
            'status' => 'disponible',
            'location_name' => 'Sala de prueba',
            'responsible_name' => 'Equipo QA',
            'acquisition_date' => '2026-06-01',
            'reference_value' => 799990,
            'observations' => 'Alta desde prueba automatizada.',
            'active' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.internal_code', 'INF-IT-TEST-9001');

        $this->assertDatabaseHas('it_equipment', [
            'internal_code' => 'INF-IT-TEST-9001',
            'equipment_type' => 'notebook',
            'status' => 'disponible',
        ]);
    }

    public function test_cannot_create_loan_for_non_available_equipment(): void
    {
        $this->seedAndActAsSuperAdmin();

        $equipment = ItEquipment::query()->where('status', 'prestado')->firstOrFail();

        $response = $this->postJson('/api/informatica/prestamos', [
            'it_equipment_id' => $equipment->id,
            'requester_type' => 'externo',
            'requester_name' => 'Solicitante de prueba',
            'requester_contact' => 'externo@example.test',
            'borrowed_at' => '2026-06-30 10:00:00',
            'due_at' => '2026-07-02 10:00:00',
            'purpose' => 'Prueba automatizada de bloqueo.',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['it_equipment_id']);
    }

    public function test_can_register_loan_return_and_restore_equipment_status(): void
    {
        $this->seedAndActAsSuperAdmin();

        $loan = ItEquipmentLoan::query()->firstWhere('loan_code', 'INF-PRE-SEED-001');
        $this->assertNotNull($loan);

        $response = $this->postJson("/api/informatica/prestamos/{$loan->id}/return", [
            'returned_at' => '2026-06-30 12:00:00',
            'return_condition' => 'bueno',
            'return_notes' => 'Devuelto correctamente desde prueba automatizada.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'devuelto');

        $loan->refresh();
        $loan->equipment->refresh();

        $this->assertSame('devuelto', $loan->status);
        $this->assertSame('disponible', $loan->equipment->status);
    }

    public function test_can_close_maintenance_and_update_equipment_status(): void
    {
        $this->seedAndActAsSuperAdmin();

        $report = ItEquipmentMaintenanceReport::query()->firstWhere('maintenance_code', 'INF-MAN-SEED-001');
        $this->assertNotNull($report);

        $response = $this->postJson("/api/informatica/mantenciones/{$report->id}/close", [
            'final_equipment_status' => 'disponible',
            'closed_at' => '2026-06-30 15:00:00',
            'observations' => 'Cierre automatizado para validar cambio de estado.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'cerrado')
            ->assertJsonPath('data.final_equipment_status', 'disponible');

        $report->refresh();
        $report->equipment->refresh();

        $this->assertSame('cerrado', $report->status);
        $this->assertSame('disponible', $report->equipment->status);
    }

    private function seedAndActAsSuperAdmin(): User
    {
        $this->seed(InformaticaSeeder::class);

        $user = User::query()->where('email', 'superadmin@cnscgestion.cl')->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }
}
