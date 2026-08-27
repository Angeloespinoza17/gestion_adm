<?php

namespace Tests\Feature\RiskPrevention;

use App\Models\Cargo;
use App\Models\InventoryItem;
use App\Models\Permission;
use App\Models\RiskPrevention\RiskPreventionAccident;
use App\Models\RiskPrevention\RiskPreventionJointCommittee;
use App\Models\RiskPrevention\RiskPreventionJointCommitteeDocument;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\Modules\PrevencionRiesgosModuleSeeder;
use Database\Seeders\PermissionGroupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiskPreventionOperationalExpansionTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(PrevencionRiesgosModuleSeeder::class);
        $this->seed(PermissionGroupSeeder::class);
        $this->manager = User::query()->where('email', 'superadmin@cnscgestion.cl')->firstOrFail();
        Sanctum::actingAs($this->manager);

        $cargo = Cargo::query()->create([
            'name' => 'Asistente preventiva',
            'slug' => 'asistente-preventiva-expansion',
            'active' => true,
        ]);
        $this->staff = Staff::query()->create([
            'full_name' => 'Carolina Seguridad',
            'rut' => '15.222.333-4',
            'cargo_id' => $cargo->id,
            'status' => 'activo',
            'active' => true,
        ]);
    }

    public function test_secretary_permission_can_upload_private_committee_minutes_without_full_prevention_management(): void
    {
        $committee = $this->createCommittee();
        $secretaryRole = Role::query()->create([
            'name' => 'Secretaría Comité Paritario',
            'slug' => 'secretaria_comite_paritario_test',
            'active' => true,
        ]);
        $secretaryRole->permissions()->attach(
            Permission::query()
                ->where('slug', 'cargar_actas_comite_paritario')
                ->pluck('id'),
        );
        $secretary = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $secretary->roles()->attach($secretaryRole);
        Sanctum::actingAs($secretary);

        $this->assertFalse($secretary->fresh()->hasPermission('gestionar_prevencion_riesgos'));
        $this->assertFalse($secretary->fresh()->hasPermission('ver_comite_paritario'));
        $this->getJson('/api/risk-prevention/joint-committees')
            ->assertOk()
            ->assertJsonPath('permissions.can_upload_minutes', true);

        $response = $this->post(
            "/api/risk-prevention/joint-committees/{$committee->id}/documents",
            [
                'document_type' => 'acta_mensual',
                'document_date' => '2026-08-18',
                'title' => 'Sesión ordinaria agosto',
                'notes' => 'Acta validada por presidencia.',
                'file' => UploadedFile::fake()->create('acta-agosto.pdf', 180, 'application/pdf'),
            ],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.period_key', '2026-08')
            ->assertJsonPath('data.uploaded_by.id', $secretary->id)
            ->assertJsonMissingPath('data.file_path');

        $path = RiskPreventionJointCommitteeDocument::query()->firstOrFail()->file_path;
        Storage::disk('local')->assertExists($path);

        $documentId = $response->json('data.id');
        $this->get("/api/risk-prevention/joint-committees/{$committee->id}/documents/{$documentId}/download")
            ->assertOk()
            ->assertDownload('acta-agosto.pdf');

        $this->withHeader('Accept', 'application/json')->post(
            "/api/risk-prevention/joint-committees/{$committee->id}/documents",
            [
                'document_type' => 'acta_mensual',
                'document_date' => '2026-08-29',
                'file' => UploadedFile::fake()->create('duplicada.pdf', 100, 'application/pdf'),
            ],
        )->assertUnprocessable()->assertJsonValidationErrors('document_date');

        $moduleSlugs = collect($this->getJson('/api/me/modules')->assertOk()->json('data'))->pluck('slug');
        $this->assertContains('risk_prevention_joint_committee', $moduleSlugs);
    }

    public function test_committee_api_includes_trainings_registered_in_the_training_module(): void
    {
        $committee = $this->createCommittee();

        $training = $this->postJson('/api/risk-prevention/trainings', [
            'name' => 'Investigación de accidentes para integrantes',
            'joint_committee_id' => $committee->id,
            'training_type' => 'obligatoria',
            'training_date' => '2026-08-20',
            'modality' => 'Presencial',
            'is_requirement' => false,
            'participants' => [[
                'staff_id' => $this->staff->id,
                'compliance_status' => 'cumplido',
                'issued_on' => '2026-08-20',
            ]],
        ])->assertCreated();

        $trainingId = $training->json('data.id');
        $this->assertDatabaseHas('prevent_trainings', [
            'id' => $trainingId,
            'joint_committee_id' => $committee->id,
        ]);

        $this->getJson('/api/risk-prevention/joint-committees')
            ->assertOk()
            ->assertJsonPath('data.0.trainings.0.id', $trainingId)
            ->assertJsonPath('data.0.trainings.0.participants.0.staff_id', $this->staff->id)
            ->assertJsonPath('data.0.summary.committee_trainings', 1)
            ->assertJsonPath('data.0.summary.trained_participants', 1);
    }

    public function test_staff_accidents_and_occupational_disease_calculate_annual_lost_days(): void
    {
        RiskPreventionAccident::query()->create([
            'occurred_at' => '2026-01-12 08:00:00',
            'accident_type' => 'staff',
            'event_type' => 'accidente',
            'involved_person_name' => 'Carolina Seguridad',
            'involved_person_identifier' => $this->staff->rut,
            'location' => 'Patio',
            'description' => 'Registro histórico anterior a la vinculación con Personal.',
            'lost_days' => 2,
            'case_status' => 'cerrado',
            'created_by' => $this->manager->id,
            'updated_by' => $this->manager->id,
        ]);

        $base = [
            'accident_type' => 'staff',
            'staff_id' => $this->staff->id,
            'location' => 'Taller',
            'description' => 'Caso ocupacional de prueba.',
            'case_status' => 'en_seguimiento',
        ];

        $this->postJson('/api/risk-prevention/accidents', array_merge($base, [
            'occurred_at' => '2026-03-10 10:00:00',
            'event_type' => 'accidente',
            'involved_person_name' => 'Debe reemplazarse',
            'injuries' => 'Esguince',
            'injured_body_part' => 'Tobillo derecho',
            'lost_days' => 3,
        ]))->assertCreated()->assertJsonPath('data.involved_person_name', 'Carolina Seguridad');

        $this->postJson('/api/risk-prevention/accidents', array_merge($base, [
            'occurred_at' => '2026-07-14 09:00:00',
            'event_type' => 'enfermedad_profesional',
            'involved_person_name' => 'Debe reemplazarse',
            'injuries' => 'Dolencia musculoesquelética',
            'injured_body_part' => 'Espalda',
            'lost_days' => 5,
        ]))->assertCreated();

        $response = $this->getJson('/api/risk-prevention/accidents?accident_type=staff&summary_year=2026')
            ->assertOk()
            ->assertJsonPath('summary.total_cases', 3)
            ->assertJsonPath('summary.lost_days', 10)
            ->assertJsonPath('summary.accidents', 2)
            ->assertJsonPath('summary.occupational_diseases', 1);

        $this->assertSame([10, 10, 10], collect($response->json('data'))->pluck('annual_lost_days')->all());
        $this->assertContains('Espalda', collect($response->json('data'))->pluck('injured_body_part')->all());
    }

    public function test_warehouse_epp_delivery_uses_inventory_stock_and_creates_auditable_output(): void
    {
        $inventoryItem = InventoryItem::query()->create([
            'code' => 'EPP-INV-001',
            'qr_code' => 'EPP-INV-001',
            'name' => 'Guante dieléctrico',
            'item_type' => 'consumable',
            'stock_quantity' => 12,
            'minimum_stock' => 3,
            'unit_of_measure' => 'par',
            'active' => true,
            'created_by' => $this->manager->id,
            'updated_by' => $this->manager->id,
        ]);

        $epp = $this->postJson('/api/risk-prevention/epp/items', [
            'inventory_item_id' => $inventoryItem->id,
            'name' => 'Nombre descartado por Bodega',
            'epp_type' => 'Protección de manos',
            'stock' => 999,
            'minimum_stock' => 999,
            'unit' => 'unidad',
            'active' => true,
        ])->assertCreated();

        $eppId = $epp->json('data.id');
        $this->postJson('/api/risk-prevention/epp/delivery-records', [
            'staff_id' => $this->staff->id,
            'employee_name' => 'Carolina Seguridad',
            'delivered_at' => '2026-08-25',
            'received_conformity' => true,
            'items' => [[
                'epp_item_id' => $eppId,
                'quantity' => 2,
                'replacement_due_at' => null,
            ]],
        ])->assertCreated();

        $this->assertSame('10.00', $inventoryItem->fresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_stock_movements', [
            'inventory_item_id' => $inventoryItem->id,
            'movement_type' => 'out',
            'quantity' => 2,
            'previous_stock' => 12,
            'new_stock' => 10,
        ]);

        $this->getJson('/api/risk-prevention/epp/items')
            ->assertOk()
            ->assertJsonPath('data.0.stock_source', 'bodega')
            ->assertJsonPath('data.0.available_stock', 10)
            ->assertJsonPath('data.0.inventory_item.code', 'EPP-INV-001');
    }

    private function createCommittee(): RiskPreventionJointCommittee
    {
        $committee = RiskPreventionJointCommittee::query()->create([
            'name' => 'Comité Paritario 2026-2028',
            'starts_on' => '2026-08-01',
            'ends_on' => '2028-07-31',
            'active' => true,
            'created_by' => $this->manager->id,
            'updated_by' => $this->manager->id,
        ]);
        $committee->staffMembers()->attach($this->staff->id, [
            'representation' => 'trabajadores',
            'member_role' => 'titular',
            'position_name' => 'Presidenta',
            'joined_on' => '2026-08-01',
            'active' => true,
        ]);

        return $committee;
    }
}
