<?php

namespace Tests\Feature\RiskPrevention;

use App\Enums\RiskPrevention\RiskMatrixStatus;
use App\Models\RiskPrevention\PreventiveProgram;
use App\Models\RiskPrevention\PreventiveProgramAction;
use App\Models\RiskPrevention\RiskAuditLog;
use App\Models\RiskPrevention\RiskCatalogItem;
use App\Models\RiskPrevention\RiskMatrix;
use App\Models\RiskPrevention\RiskMatrixVersion;
use App\Models\RiskPrevention\RiskMethodology;
use App\Models\User;
use Database\Seeders\Modules\PrevencionRiesgosModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiskMatrixWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PrevencionRiesgosModuleSeeder::class);
        $this->actor = User::query()->where('email', 'superadmin@cnscgestion.cl')->firstOrFail();
        Sanctum::actingAs($this->actor);
    }

    public function test_complete_vep_workflow_is_versioned_audited_exportable_and_immutable(): void
    {
        Storage::fake('local');

        $created = $this->postJson('/api/risk-prevention/risk-matrices', [
            'code' => 'IPER-TEST-001',
            'name' => 'Matriz institucional anonimizada',
            'company_name' => 'Organización de Prueba',
            'work_center_name' => 'Centro de Trabajo Norte',
            'prepared_on' => '2026-08-20',
            'total_workers' => 25,
            'program_responsible_id' => $this->actor->id,
        ])->assertCreated();

        $matrixId = $created->json('data.matrix.id');
        $versionId = $created->json('data.version.id');
        $lockVersion = $created->json('data.version.lock_version');
        $familyId = RiskCatalogItem::query()->type('risk_family')->where('code', 'work_safety')->valueOrFail('id');
        $exposureId = RiskCatalogItem::query()->type('exposure_category')->where('code', 'women')->valueOrFail('id');
        $protocolId = RiskCatalogItem::query()->type('protocol')->where('code', 'external_configurable')->valueOrFail('id');

        $saved = $this->putJson("/api/risk-prevention/risk-matrix-versions/{$versionId}/structure", [
            'lock_version' => $lockVersion,
            'processes' => [$this->validStructure($familyId, $exposureId, $protocolId)],
        ])->assertOk()
            ->assertJsonPath('data.processes.0.tasks.0.risks.0.assessments.0.calculated_score', 8)
            ->assertJsonPath('data.processes.0.tasks.0.risks.0.assessments.0.result_level', 'Importante');

        $this->assertSame(2, $saved->json('data.lock_version'));

        $this->postJson("/api/risk-prevention/risk-matrix-versions/{$versionId}/submit", ['reason' => 'Validación técnica'])
            ->assertOk()->assertJsonPath('data.status', RiskMatrixStatus::InReview->value);
        $this->postJson("/api/risk-prevention/risk-matrix-versions/{$versionId}/review", ['notes' => 'Antecedentes y medidas revisados.'])
            ->assertOk();
        $approved = $this->postJson("/api/risk-prevention/risk-matrix-versions/{$versionId}/approve")
            ->assertOk()->assertJsonPath('data.status', RiskMatrixStatus::Approved->value);

        $this->assertNotEmpty($approved->json('data.snapshot_hash'));
        $this->assertDatabaseHas('prevent_preventive_programs', ['risk_matrix_version_id' => $versionId]);
        $this->assertSame(1, PreventiveProgramAction::query()->where('risk_entry_id', '!=', null)->count());
        $this->assertDatabaseHas('prevent_risk_audit_logs', ['action' => 'approved']);

        $this->patchJson("/api/risk-prevention/risk-matrix-versions/{$versionId}", [
            'lock_version' => 2,
            'company_name_snapshot' => 'Intento de alteración',
        ])->assertForbidden();
        $this->putJson("/api/risk-prevention/risk-matrix-versions/{$versionId}/structure", [
            'lock_version' => 2,
            'processes' => [],
        ])->assertForbidden();

        $export = $this->get("/api/risk-prevention/risk-matrix-versions/{$versionId}/export/xlsx");
        $export->assertOk();
        $this->assertStringStartsWith('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', (string) $export->headers->get('content-type'));

        $versionTwo = $this->postJson("/api/risk-prevention/risk-matrices/{$matrixId}/versions", [
            'source_version_id' => $versionId,
            'reason' => 'Revisión anual de prueba',
        ])->assertCreated()->json('data');

        $this->assertSame(2, $versionTwo['version_number']);
        $this->assertSame(RiskMatrixStatus::Draft->value, $versionTwo['status']);
        $this->assertSame(RiskMatrixStatus::Approved, RiskMatrixVersion::query()->findOrFail($versionId)->status);

        $versionTwoId = $versionTwo['id'];
        $this->postJson("/api/risk-prevention/risk-matrix-versions/{$versionTwoId}/submit", ['reason' => 'Revisión anual'])
            ->assertOk();
        $this->postJson("/api/risk-prevention/risk-matrix-versions/{$versionTwoId}/review", ['notes' => 'Segunda versión revisada.'])
            ->assertOk();
        $this->postJson("/api/risk-prevention/risk-matrix-versions/{$versionTwoId}/approve")
            ->assertOk();

        $this->assertSame(RiskMatrixStatus::Superseded, RiskMatrixVersion::query()->findOrFail($versionId)->status);
        $this->assertSame(RiskMatrixStatus::Approved, RiskMatrixVersion::query()->findOrFail($versionTwoId)->status);
        $this->assertSame($versionTwoId, RiskMatrix::query()->findOrFail($matrixId)->active_version_id);
        $this->assertGreaterThanOrEqual(8, RiskAuditLog::query()->where('company_key', config('risk_matrix.company.key'))->count());

        $comparison = $this->getJson("/api/risk-prevention/risk-matrix-versions/{$versionId}/compare/{$versionTwoId}")
            ->assertOk()->json('data.summary');
        $this->assertSame(['added' => 0, 'removed' => 0, 'modified' => 0], $comparison);
    }

    public function test_policy_hides_a_matrix_from_another_institution(): void
    {
        $foreign = RiskMatrix::query()->create([
            'company_key' => 'otra-institucion',
            'company_name' => 'Institución ajena',
            'code' => 'IPER-FOREIGN',
            'name' => 'No visible',
            'created_by' => $this->actor->id,
        ]);
        $foreignVersion = RiskMatrixVersion::query()->create([
            'risk_matrix_id' => $foreign->id,
            'methodology_id' => RiskMethodology::query()->where('active', true)->valueOrFail('id'),
            'version_number' => 1,
            'status' => RiskMatrixStatus::Draft,
            'company_name_snapshot' => 'Institución ajena',
            'work_center_name_snapshot' => 'Centro ajeno',
            'prepared_on' => now()->toDateString(),
            'updated_on' => now()->toDateString(),
            'program_responsible_name_snapshot' => 'Persona ajena',
            'prepared_by' => $this->actor->id,
            'lock_version' => 1,
        ]);
        $foreignProgram = PreventiveProgram::query()->create([
            'risk_matrix_version_id' => $foreignVersion->id,
            'company_key' => 'otra-institucion',
            'code' => 'PTP-FOREIGN-V1',
            'name' => 'Programa ajeno',
            'status' => 'draft',
            'generated_at' => now(),
            'due_to_be_prepared_at' => now()->addMonth()->toDateString(),
        ]);

        $this->getJson("/api/risk-prevention/risk-matrices/{$foreign->id}")->assertForbidden();
        $this->getJson('/api/risk-prevention/risk-matrices')->assertOk()->assertJsonMissing(['code' => 'IPER-FOREIGN']);
        $this->getJson('/api/risk-prevention/risk-matrices/dashboard')->assertOk()->assertJsonPath('data.metrics.draft_matrices', 0);
        $this->getJson("/api/risk-prevention/preventive-programs/{$foreignProgram->id}")->assertNotFound();
    }

    public function test_portfolio_uses_latest_version_for_unapproved_drafts(): void
    {
        [$versionId, $lockVersion, $familyId, $exposureId, $protocolId] = $this->createDraft('IPER-DRAFT-PORTFOLIO');

        $this->putJson("/api/risk-prevention/risk-matrix-versions/{$versionId}/structure", [
            'lock_version' => $lockVersion,
            'processes' => [$this->validStructure($familyId, $exposureId, $protocolId)],
        ])->assertOk();

        $matrix = RiskMatrixVersion::query()->findOrFail($versionId)->matrix;
        $this->assertNull($matrix->active_version_id);

        $response = $this->getJson('/api/risk-prevention/risk-matrices?status=draft')
            ->assertOk();
        $draft = collect($response->json('data'))->firstWhere('code', 'IPER-DRAFT-PORTFOLIO');

        $this->assertNotNull($draft);
        $this->assertSame($versionId, $draft['active_version']['id']);
        $this->assertSame('draft', $draft['active_version']['status']);
        $this->assertSame(2, $draft['risk_count']);
        $this->assertSame(1, $draft['important_count']);
    }

    public function test_important_risk_without_measure_responsible_and_due_date_cannot_be_submitted(): void
    {
        [$versionId, $lockVersion, $familyId, $exposureId, $protocolId] = $this->createDraft('IPER-BLOCK-IMPORTANT');
        $structure = $this->validStructure($familyId, $exposureId, $protocolId);
        $structure['tasks'][0]['risks'][0]['controls'] = [];

        $this->putJson("/api/risk-prevention/risk-matrix-versions/{$versionId}/structure", [
            'lock_version' => $lockVersion,
            'processes' => [$structure],
        ])->assertOk();

        $this->postJson("/api/risk-prevention/risk-matrix-versions/{$versionId}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('matrix');
        $this->assertSame(RiskMatrixStatus::Draft, RiskMatrixVersion::query()->findOrFail($versionId)->status);
    }

    public function test_intolerable_risk_requires_audited_exception_and_additional_approval(): void
    {
        [$versionId, $lockVersion, $familyId, $exposureId, $protocolId] = $this->createDraft('IPER-BLOCK-INTOLERABLE');
        $structure = $this->validStructure($familyId, $exposureId, $protocolId);
        $structure['tasks'][0]['risks'][0]['assessments'][0]['probability'] = 4;
        $structure['tasks'][0]['risks'][0]['assessments'][0]['consequence'] = 4;

        $this->putJson("/api/risk-prevention/risk-matrix-versions/{$versionId}/structure", [
            'lock_version' => $lockVersion,
            'processes' => [$structure],
        ])->assertOk();
        $this->postJson("/api/risk-prevention/risk-matrix-versions/{$versionId}/submit")->assertOk();
        $this->postJson("/api/risk-prevention/risk-matrix-versions/{$versionId}/review", ['notes' => 'Respuesta inmediata revisada.'])->assertOk();

        $this->postJson("/api/risk-prevention/risk-matrix-versions/{$versionId}/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('exception');
        $this->postJson("/api/risk-prevention/risk-matrix-versions/{$versionId}/approve", [
            'justification' => 'Excepción ficticia documentada para validar el control del flujo.',
            'additional_approval' => true,
        ])->assertOk()->assertJsonPath('data.status', RiskMatrixStatus::Approved->value);

        $this->assertDatabaseHas('prevent_risk_audit_logs', ['action' => 'approval_block_overridden']);
    }

    private function createDraft(string $code): array
    {
        $created = $this->postJson('/api/risk-prevention/risk-matrices', [
            'code' => $code,
            'name' => 'Matriz ficticia para bloqueo',
            'company_name' => 'Organización de Prueba',
            'work_center_name' => 'Centro de Trabajo Norte',
            'program_responsible_id' => $this->actor->id,
        ])->assertCreated();

        return [
            $created->json('data.version.id'),
            $created->json('data.version.lock_version'),
            RiskCatalogItem::query()->type('risk_family')->where('code', 'work_safety')->valueOrFail('id'),
            RiskCatalogItem::query()->type('exposure_category')->where('code', 'women')->valueOrFail('id'),
            RiskCatalogItem::query()->type('protocol')->where('code', 'external_configurable')->valueOrFail('id'),
        ];
    }

    private function validStructure(int $familyId, int $exposureId, int $protocolId): array
    {
        return [
            'name' => 'Operación de prueba',
            'process_type' => 'operational',
            'tasks' => [[
                'activity_name' => 'Preparación de material',
                'task_name' => 'Traslado controlado',
                'routine_type' => 'routine',
                'job_position_text' => 'Cargo anonimizado',
                'specific_location' => 'Zona de prueba',
                'exposures' => [['exposure_category_id' => $exposureId, 'count' => 3]],
                'risks' => [
                    [
                        'risk_family_id' => $familyId,
                        'specific_risk_name' => 'Caída al mismo nivel',
                        'possible_harm' => 'Lesión musculoesquelética',
                        'evaluation_method' => 'vep',
                        'hazard_factors' => [['category' => 'environment', 'hazard_description' => 'Superficie irregular', 'risk_factor_description' => 'Tránsito por área delimitada']],
                        'assessments' => [
                            ['phase' => 'current', 'method' => 'vep', 'probability' => 2, 'consequence' => 4],
                            ['phase' => 'residual', 'method' => 'vep', 'probability' => 1, 'consequence' => 2],
                        ],
                        'controls' => [[
                            'control_stage' => 'preventive',
                            'hierarchy_type' => 'engineering',
                            'description' => 'Regularizar la superficie y segregar el tránsito.',
                            'responsible_user_id' => $this->actor->id,
                            'status' => 'planned',
                            'priority' => 'high',
                            'planned_start_date' => '2026-08-21',
                            'due_date' => '2026-09-15',
                            'periodicity_type' => 'once',
                            'progress_percentage' => 0,
                            'creates_program_action' => true,
                        ]],
                    ],
                    [
                        'risk_family_id' => $familyId,
                        'specific_risk_name' => 'Exposición específica medida',
                        'possible_harm' => 'Efecto sujeto a protocolo',
                        'evaluation_method' => 'protocol',
                        'legal_or_protocol_reference' => 'PROTOCOLO-ANON-1',
                        'hazard_factors' => [['category' => 'environment', 'hazard_description' => 'Agente medible', 'risk_factor_description' => 'Exposición controlada']],
                        'assessments' => [[
                            'phase' => 'current',
                            'method' => 'protocol',
                            'protocol_id' => $protocolId,
                            'protocol_version' => '1.0',
                            'result_level' => 'Bajo control con seguimiento',
                            'instrument' => 'Instrumento anonimizado',
                            'instrument_date' => '2026-08-20',
                            'next_measurement_at' => '2027-08-20',
                        ]],
                        'controls' => [],
                    ],
                ],
            ]],
        ];
    }
}
