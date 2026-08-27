<?php

namespace Tests\Feature\Maintenance;

use App\Models\MaintenanceDependency;
use App\Models\MaintenanceWorkOrder;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaintenanceWorkOrderClosureTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->user = $this->authorizedUser(['editar_ot', 'ver_mantencion']);
        Sanctum::actingAs($this->user);
    }

    public function test_work_order_moves_to_pending_closure_before_it_can_be_closed(): void
    {
        $workOrder = $this->workOrder();

        $this->postJson("/api/maintenance/work-orders/{$workOrder->id}/close", [
            'resolution_notes' => 'Trabajo terminado.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->postJson("/api/maintenance/work-orders/{$workOrder->id}/request-closure")
            ->assertOk()
            ->assertJsonPath('data.status', 'Terminado')
            ->assertJsonPath('data.resolution_notes', null)
            ->assertJsonPath('data.closed_at', null);

        $this->assertDatabaseHas('maintenance_work_orders', [
            'id' => $workOrder->id,
            'status' => 'Terminado',
            'resolution_notes' => null,
            'closed_at' => null,
        ]);
    }

    public function test_closure_requires_notes_and_records_document_and_audit_data(): void
    {
        $workOrder = $this->workOrder(['status' => 'Terminado']);

        $this->postJson("/api/maintenance/work-orders/{$workOrder->id}/close")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('resolution_notes');

        $response = $this->post(
            "/api/maintenance/work-orders/{$workOrder->id}/close",
            [
                'resolution_notes' => 'Se reparó la instalación y se verificó su funcionamiento.',
                'closure_document' => UploadedFile::fake()->create('acta-cierre.pdf', 120, 'application/pdf'),
            ],
            ['Accept' => 'application/json']
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'Terminado')
            ->assertJsonPath('data.closed_by_user.id', $this->user->id)
            ->assertJsonPath('data.closure_document_original_name', 'acta-cierre.pdf');

        $workOrder->refresh();

        $this->assertNotNull($workOrder->closed_at);
        $this->assertSame($this->user->id, $workOrder->closed_by_user_id);
        $this->assertSame('Se reparó la instalación y se verificó su funcionamiento.', $workOrder->resolution_notes);
        $this->assertStringStartsWith('maintenance/work-orders/closures/', $workOrder->closure_document_reference);
        $this->assertStringEndsWith('.pdf', $workOrder->closure_document_reference);
        $this->assertSame('/storage/'.$workOrder->closure_document_reference, $workOrder->closure_document_url);
        Storage::disk('public')->assertExists($workOrder->closure_document_reference);
    }

    public function test_pending_closure_queue_and_summary_are_available(): void
    {
        $pendingBefore = MaintenanceWorkOrder::pendingClosure()->count();
        $finishedBefore = MaintenanceWorkOrder::closedWithNote()->count();
        $description = 'Definición de cola de cierre 20260810.';
        $pending = $this->workOrder(['status' => 'Terminado', 'description' => $description]);
        $pendingBlank = $this->workOrder([
            'status' => 'Terminado',
            'resolution_notes' => '   ',
            'description' => $description,
        ]);
        $this->workOrder(['status' => 'En proceso', 'description' => $description]);
        $completed = $this->workOrder([
            'status' => 'Terminado',
            'resolution_notes' => 'Cierre histórico.',
            'description' => $description,
        ]);

        $this->getJson('/api/maintenance/work-orders?queue=pending_closure&search='.urlencode($description))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $pending->id])
            ->assertJsonFragment(['id' => $pendingBlank->id]);

        $this->getJson('/api/maintenance/work-orders?queue=completed&search='.urlencode($description))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $completed->id);

        $this->getJson('/api/maintenance/work-orders/catalogs')
            ->assertOk()
            ->assertJsonPath('summary.pending_closure', $pendingBefore + 2)
            ->assertJsonPath('summary.finished', $finishedBefore + 1)
            ->assertJsonPath('statuses', ['Sin comenzar', 'En proceso', 'En espera', 'Pausado', 'Terminado', 'Anulado']);
    }

    public function test_mobile_dependency_selector_uses_a_small_searchable_catalog(): void
    {
        $library = $this->maintenanceDependency('DEP-BIB-01', 'Biblioteca CRA', [
            'sector' => 'Primer piso',
            'usage' => 'Lectura y recursos',
        ]);
        $gym = $this->maintenanceDependency('DEP-GIM-01', 'Gimnasio principal');
        $this->maintenanceDependency('DEP-INACTIVA', 'Biblioteca antigua', ['active' => false]);
        $this->maintenanceDependency('AREA-BIB-01', 'Tablero biblioteca', [
            'dependency_kind' => MaintenanceDependency::KIND_TECHNICAL_ASSET,
            'is_maintenance_location' => false,
        ]);

        $this->getJson('/api/maintenance/work-orders/dependency-options?search='.urlencode('biblioteca lectura'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $library->id)
            ->assertJsonPath('data.0.code', 'DEP-BIB-01')
            ->assertJsonPath('data.0.usage', 'Lectura y recursos')
            ->assertJsonPath('meta.limit', 40);

        $selectedResponse = $this->getJson(
            '/api/maintenance/work-orders/dependency-options?search='.urlencode('gimnasio').
            '&selected_id='.$library->id
        );

        $selectedResponse
            ->assertOk()
            ->assertJsonFragment(['id' => $library->id, 'code' => 'DEP-BIB-01'])
            ->assertJsonFragment(['id' => $gym->id, 'code' => 'DEP-GIM-01']);

        $this->getJson('/api/maintenance/work-orders/catalogs?include_dependencies=0')
            ->assertOk()
            ->assertJsonMissingPath('dependencies');
    }

    private function workOrder(array $overrides = []): MaintenanceWorkOrder
    {
        return MaintenanceWorkOrder::query()->create(array_merge([
            'priority' => 'Media',
            'status' => 'En proceso',
            'description' => 'Reparación de prueba.',
        ], $overrides));
    }

    private function maintenanceDependency(string $code, string $name, array $overrides = []): MaintenanceDependency
    {
        return MaintenanceDependency::query()->create(array_merge([
            'dependency_kind' => MaintenanceDependency::KIND_SPACE,
            'code' => $code,
            'name' => $name,
            'active' => true,
            'is_maintenance_location' => true,
        ], $overrides));
    }

    private function authorizedUser(array $permissionSlugs): User
    {
        $user = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $role = Role::query()->create([
            'name' => 'Gestión de cierres OT',
            'slug' => 'gestion-cierres-ot',
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
