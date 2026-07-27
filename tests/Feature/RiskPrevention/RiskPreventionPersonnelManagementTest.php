<?php

namespace Tests\Feature\RiskPrevention;

use App\Models\Cargo;
use App\Models\Department;
use App\Models\RiskPrevention\RiskPreventionStaffCompliance;
use App\Models\RiskPrevention\RiskPreventionStaffRequirementType;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\Modules\PrevencionRiesgosModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiskPreventionPersonnelManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(PrevencionRiesgosModuleSeeder::class);
        $this->manager = User::query()->where('email', 'superadmin@cnscgestion.cl')->firstOrFail();
        Sanctum::actingAs($this->manager);

        $cargo = Cargo::query()->create([
            'name' => 'Asistente de la educación',
            'slug' => 'asistente-prevencion-test',
            'active' => true,
        ]);
        $this->staff = Staff::query()->create([
            'full_name' => 'Daniela Prevención',
            'rut' => '18.111.222-3',
            'cargo_id' => $cargo->id,
            'status' => 'activo',
            'active' => true,
        ]);
    }

    public function test_manager_can_create_a_dynamic_requirement_and_update_individual_compliance(): void
    {
        $response = $this->postJson('/api/risk-prevention/personnel/requirement-types', [
            'name' => 'Trabajo en altura',
            'code' => 'TRABAJO_ALTURA',
            'kind' => 'document',
            'validity_months' => 12,
            'requires_evidence' => true,
            'is_mandatory' => true,
            'active' => true,
            'sort_order' => 50,
            'description' => 'Certificación para trabajos en altura.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.code', 'TRABAJO_ALTURA');

        $requirementId = $response->json('data.id');

        $complianceResponse = $this->post(
            "/api/risk-prevention/personnel/staff/{$this->staff->id}/requirements/{$requirementId}/compliance",
            [
                'issued_on' => '2026-07-01',
                'is_not_applicable' => '0',
                'notes' => 'Certificado revisado.',
                'evidence' => UploadedFile::fake()->create('certificado-altura.pdf', 120, 'application/pdf'),
            ],
        );

        $complianceResponse
            ->assertCreated()
            ->assertJsonPath('data.current_status', 'vigente')
            ->assertJsonPath('data.expires_on', '2027-07-01')
            ->assertJsonPath('data.has_evidence', true);

        $compliance = RiskPreventionStaffCompliance::query()->firstOrFail();
        Storage::disk('local')->assertExists($compliance->evidence_path);

        $this->getJson('/api/risk-prevention/personnel/matrix')
            ->assertOk()
            ->assertJsonPath('data.0.full_name', 'Daniela Prevención')
            ->assertJsonPath("data.0.compliances.{$requirementId}.current_status", 'vigente')
            ->assertJsonFragment(['code' => 'TRABAJO_ALTURA']);
    }

    public function test_training_evidence_updates_the_linked_staff_requirement(): void
    {
        $requirement = RiskPreventionStaffRequirementType::query()
            ->where('code', 'PRIMEROS_AUXILIOS')
            ->firstOrFail();

        $response = $this->post('/api/risk-prevention/trainings', [
            'name' => 'Curso práctico de primeros auxilios',
            'requirement_type_id' => $requirement->id,
            'training_type' => 'obligatoria',
            'training_date' => '2026-07-15',
            'modality' => 'Presencial',
            'observations' => 'Actividad anual.',
            'evidence' => UploadedFile::fake()->create('lista-asistencia.pdf', 100, 'application/pdf'),
            'participants' => [
                [
                    'staff_id' => $this->staff->id,
                    'employee_name' => 'Nombre que no debe prevalecer',
                    'compliance_status' => 'cumplido',
                    'issued_on' => '2026-07-15',
                    'expires_on' => '2028-07-15',
                    'notes' => 'Aprobado.',
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.participants.0.staff_id', $this->staff->id)
            ->assertJsonPath('data.participants.0.employee_name', 'Daniela Prevención');

        $trainingId = $response->json('data.id');

        $this->assertDatabaseHas('prevent_staff_compliances', [
            'staff_id' => $this->staff->id,
            'requirement_type_id' => $requirement->id,
            'training_id' => $trainingId,
            'issued_on' => '2026-07-15',
            'expires_on' => '2028-07-15',
        ]);

        $this->getJson('/api/risk-prevention/personnel/matrix')
            ->assertOk()
            ->assertJsonPath(
                'data.0.training_requirements.pending_count',
                2,
            )
            ->assertJsonPath(
                'data.0.training_requirements.completed_count',
                1,
            )
            ->assertJsonFragment([
                'name' => 'Uso y manejo de extintores',
                'source' => 'catalog',
            ]);
    }

    public function test_training_catalog_groups_staff_by_department_and_matrix_requirement_is_optional(): void
    {
        $department = Department::query()->create([
            'name' => 'Administración preventiva',
            'slug' => 'administracion-preventiva',
            'active' => true,
            'sort_order' => 1,
        ]);
        $department->staff()->attach($this->staff->id);

        $catalogResponse = $this->getJson('/api/risk-prevention/catalogs')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $department->id,
                'name' => 'Administración preventiva',
                'staff_count' => 1,
            ]);

        $staffMember = collect($catalogResponse->json('staff_members'))
            ->firstWhere('id', $this->staff->id);

        $this->assertNotNull($staffMember);
        $this->assertContains($department->id, $staffMember['department_ids']);
        $this->assertContains('Administración preventiva', $staffMember['departments']);

        $trainingResponse = $this->postJson('/api/risk-prevention/trainings', [
            'name' => 'Inducción general sin matriz',
            'training_type' => 'induccion',
            'training_date' => '2026-07-27',
            'modality' => 'Presencial',
            'participants' => [
                [
                    'staff_id' => $this->staff->id,
                    'compliance_status' => 'pendiente',
                    'issued_on' => null,
                    'expires_on' => null,
                ],
            ],
        ]);

        $trainingResponse
            ->assertCreated()
            ->assertJsonPath('data.requirement_type_id', null)
            ->assertJsonPath('data.participants.0.staff_id', $this->staff->id)
            ->assertJsonPath('data.participants.0.employee_name', 'Daniela Prevención');
    }

    public function test_required_trainings_are_consolidated_in_staff_matrix_and_can_be_unmarked(): void
    {
        $pendingTraining = $this->postJson('/api/risk-prevention/trainings', [
            'name' => 'Capacitación pendiente general',
            'training_type' => 'obligatoria',
            'training_date' => '2026-08-10',
            'modality' => 'Presencial',
            'participants' => [],
        ])->assertCreated();

        $completedTraining = $this->postJson('/api/risk-prevention/trainings', [
            'name' => 'Capacitación vigente completada',
            'training_type' => 'induccion',
            'training_date' => '2026-07-01',
            'modality' => 'Presencial',
            'is_requirement' => true,
            'participants' => [
                [
                    'staff_id' => $this->staff->id,
                    'compliance_status' => 'cumplido',
                    'issued_on' => '2026-07-01',
                    'expires_on' => '2027-07-01',
                ],
            ],
        ])->assertCreated();

        $this->postJson('/api/risk-prevention/trainings', [
            'name' => 'Capacitación informativa',
            'training_type' => 'actualizacion',
            'training_date' => '2026-07-15',
            'modality' => 'Online',
            'is_requirement' => false,
            'participants' => [],
        ])->assertCreated();

        $pendingTrainingId = $pendingTraining->json('data.id');

        $this->assertDatabaseHas('prevent_trainings', [
            'id' => $pendingTrainingId,
            'is_requirement' => true,
        ]);
        $this->assertDatabaseHas('prevent_trainings', [
            'id' => $completedTraining->json('data.id'),
            'is_requirement' => true,
        ]);
        $this->assertDatabaseHas('prevent_trainings', [
            'name' => 'Capacitación informativa',
            'is_requirement' => false,
        ]);

        $this->getJson('/api/risk-prevention/personnel/matrix')
            ->assertOk()
            ->assertJsonMissing(['kind' => 'training'])
            ->assertJsonPath('data.0.training_requirements.required_count', 5)
            ->assertJsonPath('data.0.training_requirements.pending_count', 4)
            ->assertJsonPath('data.0.training_requirements.completed_count', 1)
            ->assertJsonFragment([
                'name' => 'Uso y manejo de extintores',
                'source' => 'catalog',
                'participation_status' => 'sin_registro',
            ])
            ->assertJsonFragment([
                'name' => 'Capacitación pendiente general',
                'source' => 'scheduled',
                'participation_status' => 'sin_registro',
            ]);

        $this->putJson("/api/risk-prevention/trainings/{$pendingTrainingId}", [
            'name' => 'Capacitación pendiente general',
            'training_type' => 'obligatoria',
            'training_date' => '2026-08-10',
            'modality' => 'Presencial',
            'is_requirement' => false,
            'participants' => [],
        ])->assertOk();

        $this->getJson('/api/risk-prevention/personnel/matrix')
            ->assertOk()
            ->assertJsonPath('data.0.training_requirements.required_count', 4)
            ->assertJsonPath('data.0.training_requirements.pending_count', 3)
            ->assertJsonPath('data.0.training_requirements.completed_count', 1);
    }

    public function test_manager_can_download_all_prevention_documents_for_a_staff_member(): void
    {
        $requirement = RiskPreventionStaffRequirementType::query()->where('code', 'ODI')->firstOrFail();
        $path = "risk-prevention/personnel/{$this->staff->id}/{$requirement->id}/odi.pdf";
        Storage::disk('local')->put($path, 'contenido preventivo');

        RiskPreventionStaffCompliance::query()->create([
            'staff_id' => $this->staff->id,
            'requirement_type_id' => $requirement->id,
            'issued_on' => '2026-07-01',
            'evidence_path' => $path,
            'evidence_name' => 'odi-firmada.pdf',
            'evidence_mime' => 'application/pdf',
            'evidence_size' => 20,
            'created_by' => $this->manager->id,
            'updated_by' => $this->manager->id,
        ]);

        $response = $this->get("/api/risk-prevention/personnel/staff/{$this->staff->id}/documents/download");

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/zip')
            ->assertDownload('expediente-preventivo-daniela-prevencion.zip');
    }

    public function test_staff_archive_reports_when_there_are_no_associated_documents(): void
    {
        $this->getJson(
            "/api/risk-prevention/personnel/staff/{$this->staff->id}/documents/download",
        )
            ->assertNotFound()
            ->assertJsonPath('message', 'No hay documentos asociados a este funcionario.');
    }

    public function test_joint_committee_uses_staff_pivot_and_keeps_history_when_deactivated(): void
    {
        $response = $this->postJson('/api/risk-prevention/personnel/committees', [
            'name' => 'Comité Paritario 2026-2028',
            'starts_on' => '2026-08-01',
            'ends_on' => '2028-07-31',
            'active' => true,
            'notes' => 'Período constituido.',
            'members' => [
                [
                    'staff_id' => $this->staff->id,
                    'representation' => 'trabajadores',
                    'member_role' => 'titular',
                    'position_name' => 'Presidenta',
                    'joined_on' => '2026-08-01',
                    'ended_on' => null,
                    'active' => true,
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.staff_members.0.id', $this->staff->id)
            ->assertJsonPath('data.staff_members.0.pivot.position_name', 'Presidenta');

        $committeeId = $response->json('data.id');
        $this->assertDatabaseHas('prevent_joint_committee_staff', [
            'committee_id' => $committeeId,
            'staff_id' => $this->staff->id,
            'representation' => 'trabajadores',
            'member_role' => 'titular',
            'active' => true,
        ]);

        $this->deleteJson("/api/risk-prevention/personnel/committees/{$committeeId}")
            ->assertOk()
            ->assertJsonPath('archived', true);

        $this->assertDatabaseHas('prevent_joint_committees', [
            'id' => $committeeId,
            'active' => false,
        ]);
        $this->assertDatabaseHas('prevent_joint_committee_staff', [
            'committee_id' => $committeeId,
            'staff_id' => $this->staff->id,
            'active' => false,
        ]);
    }

    public function test_requirement_with_history_is_archived_instead_of_deleted(): void
    {
        $requirement = RiskPreventionStaffRequirementType::query()->where('code', 'ODI')->firstOrFail();
        RiskPreventionStaffCompliance::query()->create([
            'staff_id' => $this->staff->id,
            'requirement_type_id' => $requirement->id,
            'issued_on' => '2026-07-01',
            'is_not_applicable' => true,
            'created_by' => $this->manager->id,
            'updated_by' => $this->manager->id,
        ]);

        $this->deleteJson("/api/risk-prevention/personnel/requirement-types/{$requirement->id}")
            ->assertOk()
            ->assertJsonPath('archived', true);

        $this->assertDatabaseHas('prevent_staff_requirement_types', [
            'id' => $requirement->id,
            'active' => false,
        ]);
        $this->assertDatabaseHas('prevent_staff_compliances', [
            'staff_id' => $this->staff->id,
            'requirement_type_id' => $requirement->id,
        ]);
    }
}
