<?php

namespace Tests\Feature\Convivencia;

use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaCatalogItem;
use App\Models\Convivencia\ConvivenciaComplaint;
use App\Models\Convivencia\ConvivenciaProtocol;
use App\Models\Convivencia\ConvivenciaProtocolActivation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Convivencia\ConvivenciaAccessService;
use Database\Seeders\ConvivenciaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConvivenciaProtocolRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_installation_state_is_cached_and_fails_closed_for_a_partial_schema(): void
    {
        $service = app(ConvivenciaAccessService::class);
        $service->clearInstallationCache();
        $missingTable = 'convivencia_protocol_activation_parts';
        $metadataChecks = 0;
        Schema::shouldReceive('hasTable')
            ->times(count($service->requiredTables()))
            ->andReturnUsing(function (string $table) use (&$metadataChecks, $missingTable): bool {
                $metadataChecks++;

                return $table !== $missingTable;
            });

        $this->assertFalse($service->isInstalled());
        $checksAfterFirstCall = $metadataChecks;
        $this->assertContains($missingTable, $service->missingTables());
        $this->assertFalse($service->isInstalled());
        $this->assertSame($checksAfterFirstCall, $metadataChecks);
        $service->clearInstallationCache();
    }

    public function test_sensitive_visibility_uses_explicit_ownership_map_without_schema_queries(): void
    {
        $this->seed(ConvivenciaSeeder::class);
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->create([
            'name' => 'Visor de privacidad con presupuesto acotado',
            'slug' => 'visor_privacidad_presupuesto_test',
            'active' => true,
        ]);
        $role->permissions()->attach(
            Permission::query()->where('slug', 'ver_casos_convivencia')->value('id')
        );
        $user->roles()->attach($role);
        $user = $user->fresh();
        Schema::shouldReceive('hasColumn')->never();

        $service = new ConvivenciaAccessService;
        for ($iteration = 0; $iteration < 8; $iteration++) {
            $caseSql = $service->applyCaseVisibility(ConvivenciaCase::query(), $user)->toSql();
            $complaintSql = $service->applyComplaintVisibility(ConvivenciaComplaint::query(), $user)->toSql();
        }

        $this->assertStringContainsString('responsible_user_id', $caseSql);
        $this->assertStringContainsString('responsible_user_id', $complaintSql);
    }

    public function test_protocol_and_reusable_parts_crud_preserves_step_ids_and_audit_history(): void
    {
        $user = $this->seedAndActAsSuperAdmin();

        $partResponse = $this->postJson('/api/convivencia/protocol-parts', [
            'category' => 'protective_measure',
            'title' => 'Resguardo inicial verificable',
            'description' => 'Acción protectora reutilizable.',
            'active' => true,
        ])->assertCreated()->assertJsonPath('data.code', 'resguardo_inicial_verificable');
        $partId = (int) $partResponse->json('data.id');

        $protocolResponse = $this->postJson('/api/convivencia/protocols', [
            'code' => 'TEST-RUNTIME-CRUD',
            'name' => 'Protocolo CRUD trazable',
            'status' => 'activo',
            'is_sensitive' => true,
            'steps' => [
                [
                    'step_order' => 1,
                    'code' => 'reception',
                    'stage_name' => 'Recepción',
                    'deadline_value' => 24,
                    'deadline_unit' => 'hours',
                    'part_links' => [[
                        'protocol_part_id' => $partId,
                        'sort_order' => 1,
                        'is_required' => true,
                    ]],
                ],
                ['step_order' => 2, 'code' => 'closure', 'stage_name' => 'Cierre'],
            ],
        ])->assertCreated()->assertJsonPath('data.revision', 1);

        $protocolId = (int) $protocolResponse->json('data.id');
        $originalStepIds = collect($protocolResponse->json('data.steps'))->pluck('id')->all();

        $this->getJson('/api/convivencia/protocols?search=TEST-RUNTIME&per_page=15')
            ->assertOk()
            ->assertJsonPath('data.0.id', $protocolId);
        $partSearch = $this->getJson('/api/convivencia/protocol-parts?search=protectora&per_page=100')
            ->assertOk();
        $this->assertContains($partId, collect($partSearch->json('data'))->pluck('id')->all());

        $this->putJson("/api/convivencia/protocols/{$protocolId}", [
            'code' => 'TEST-RUNTIME-CRUD',
            'name' => 'Protocolo sin bloqueo optimista',
            'status' => 'activo',
        ])->assertUnprocessable()->assertJsonValidationErrors('expected_revision');

        $update = $this->putJson("/api/convivencia/protocols/{$protocolId}", [
            'expected_revision' => 1,
            'code' => 'TEST-RUNTIME-CRUD',
            'name' => 'Protocolo CRUD actualizado',
            'status' => 'activo',
            'is_sensitive' => true,
            'steps' => collect($protocolResponse->json('data.steps'))->map(fn (array $step) => [
                'id' => $step['id'],
                'step_order' => $step['step_order'],
                'code' => $step['code'],
                'stage_name' => $step['stage_name'].' actualizada',
            ])->all(),
        ])->assertOk()->assertJsonPath('data.revision', 2);

        $this->assertSame($originalStepIds, collect($update->json('data.steps'))->pluck('id')->all());
        $this->assertSame('TEST-RUNTIME-CRUD', ConvivenciaProtocol::query()->findOrFail($protocolId)->code);
        $this->assertDatabaseHas('convivencia_status_logs', [
            'loggable_type' => ConvivenciaProtocol::class,
            'loggable_id' => $protocolId,
            'changed_by' => $user->id,
            'event_type' => 'definition_updated',
        ]);

        $this->deleteJson("/api/convivencia/protocol-parts/{$partId}")->assertOk();
        $this->assertSoftDeleted('convivencia_protocol_parts', ['id' => $partId]);
        $this->assertDatabaseMissing('convivencia_protocol_part_links', ['protocol_part_id' => $partId]);
        $this->assertSame(3, (int) ConvivenciaProtocol::query()->findOrFail($protocolId)->revision);

        $this->deleteJson("/api/convivencia/protocols/{$protocolId}")->assertOk();
        $this->assertSoftDeleted('convivencia_protocols', ['id' => $protocolId]);
        $this->assertDatabaseHas('convivencia_status_logs', [
            'loggable_type' => ConvivenciaProtocol::class,
            'loggable_id' => $protocolId,
            'event_type' => 'archived',
        ]);
    }

    public function test_activation_snapshots_definition_and_advances_only_after_required_evidence_and_conditions(): void
    {
        $this->seedAndActAsSuperAdmin();
        $case = ConvivenciaCase::query()->firstOrFail();
        $protocolTypeId = ConvivenciaCatalogItem::query()->where('group', 'protocol_type')->value('id');
        $criticalityId = ConvivenciaCatalogItem::query()->where('group', 'criticality')->value('id');
        $evidencePart = $this->createPart([
            'category' => 'evidence',
            'code' => 'runtime_evidence',
            'title' => 'Evidencia obligatoria',
            'requires_evidence' => true,
            'deadline_value' => 2,
            'deadline_unit' => 'business_days',
            'deadline_anchor' => 'step_started',
        ]);
        $conditionalPart = $this->createPart([
            'category' => 'sanction',
            'code' => 'runtime_preschool_block',
            'title' => 'Sanción bloqueada en párvulos',
        ]);

        $protocol = $this->postJson('/api/convivencia/protocols', [
            'code' => 'TEST-RUNTIME-FLOW',
            'name' => 'Protocolo original para snapshot',
            'description' => 'Descripción normativa original e inmutable.',
            'protocol_type_item_id' => $protocolTypeId,
            'type_label' => 'Tipo normativo original',
            'criticality_item_id' => $criticalityId,
            'criticality_label' => 'Criticidad original',
            'required_documents' => 'Documento original obligatorio',
            'safeguard_measures' => 'Resguardo original',
            'minimal_actions' => 'Acción mínima original',
            'default_due_days' => 9,
            'published_at' => '2026-03-01 10:30:00',
            'status' => 'activo',
            'is_sensitive' => true,
            'steps' => [
                [
                    'step_order' => 1,
                    'code' => 'first',
                    'stage_name' => 'Etapa original',
                    'deadline_value' => 24,
                    'deadline_unit' => 'hours',
                    'completion_rule' => ['requires_completion_note' => true],
                    'part_links' => [
                        ['protocol_part_id' => $evidencePart, 'sort_order' => 1, 'is_required' => true],
                        [
                            'protocol_part_id' => $conditionalPart,
                            'sort_order' => 2,
                            'is_required' => true,
                            'condition' => ['field' => 'education_scope', 'operator' => 'equals', 'value' => 'preschool'],
                            'configuration' => ['blocked_for_preschool_child' => true],
                        ],
                    ],
                ],
                ['step_order' => 2, 'code' => 'second', 'stage_name' => 'Cierre secuencial'],
            ],
        ])->assertCreated()->json('data');

        $activation = $this->postJson('/api/convivencia/protocol-activations', [
            'protocol_id' => $protocol['id'],
            'case_id' => $case->id,
        ])->assertCreated()
            ->assertJsonPath('data.progress.current_order', 1)
            ->assertJsonPath('data.progress.total_steps', 2)
            ->assertJsonPath('data.progress.percentage', 0)
            ->json('data');

        $this->putJson("/api/convivencia/protocol-activations/{$activation['id']}", [
            'protocol_id' => $protocol['id'],
            'case_id' => $case->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->putJson("/api/convivencia/protocol-activations/{$activation['id']}", [
            'revision' => 1,
            'protocol_id' => $protocol['id'],
            'case_id' => $case->id,
            'due_at' => now()->addMonth()->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('due_at');

        $this->assertCount(2, $activation['runtime_steps']);
        $firstStepId = (int) $activation['runtime_steps'][0]['id'];
        $evidenceRuntime = collect($activation['runtime_steps'][0]['parts'])->firstWhere('code', 'runtime_evidence');
        $blockedRuntime = collect($activation['runtime_steps'][0]['parts'])->firstWhere('code', 'runtime_preschool_block');

        $updatedSteps = collect($protocol['steps'])->map(fn (array $step) => [
            'id' => $step['id'],
            'step_order' => $step['step_order'],
            'code' => $step['code'],
            'stage_name' => $step['step_order'] === 1 ? 'Definición modificada después' : $step['stage_name'],
        ])->all();
        $this->putJson("/api/convivencia/protocols/{$protocol['id']}", [
            'expected_revision' => 1,
            'code' => 'TEST-RUNTIME-FLOW',
            'name' => 'Nombre modificado después',
            'description' => 'Descripción modificada después de activar.',
            'type_label' => 'Tipo modificado',
            'criticality_label' => 'Criticidad modificada',
            'required_documents' => 'Documentos modificados',
            'safeguard_measures' => 'Resguardos modificados',
            'minimal_actions' => 'Acciones modificadas',
            'default_due_days' => 30,
            'published_at' => '2026-04-01 12:00:00',
            'status' => 'activo',
            'is_sensitive' => true,
            'steps' => $updatedSteps,
        ])->assertOk();

        $this->getJson("/api/convivencia/protocol-activations/{$activation['id']}")
            ->assertOk()
            ->assertJsonPath('data.protocol_snapshot.name', 'Protocolo original para snapshot')
            ->assertJsonPath('data.protocol_snapshot.description', 'Descripción normativa original e inmutable.')
            ->assertJsonPath('data.protocol_snapshot.protocol_type_item_id', $protocolTypeId)
            ->assertJsonPath('data.protocol_snapshot.type_label', 'Tipo normativo original')
            ->assertJsonPath('data.protocol_snapshot.criticality_item_id', $criticalityId)
            ->assertJsonPath('data.protocol_snapshot.criticality_label', 'Criticidad original')
            ->assertJsonPath('data.protocol_snapshot.required_documents', 'Documento original obligatorio')
            ->assertJsonPath('data.protocol_snapshot.safeguard_measures', 'Resguardo original')
            ->assertJsonPath('data.protocol_snapshot.minimal_actions', 'Acción mínima original')
            ->assertJsonPath('data.protocol_snapshot.default_due_days', 9)
            ->assertJsonPath('data.protocol_snapshot.is_sensitive', true)
            ->assertJsonPath('data.protocol_snapshot.status', 'activo')
            ->assertJsonPath('data.protocol_snapshot.published_at', '2026-03-01T10:30:00-03:00')
            ->assertJsonPath('data.runtime_steps.0.stage_name', 'Etapa original');

        $this->postJson("/api/convivencia/protocol-activation-steps/{$firstStepId}/complete", [
            'revision' => 1,
            'notes' => 'Intento anticipado',
        ])
            ->assertUnprocessable()->assertJsonValidationErrors('parts');
        $this->putJson("/api/convivencia/protocol-activation-parts/{$evidenceRuntime['id']}", [
            'revision' => 1,
            'status' => 'completed',
        ])->assertUnprocessable()->assertJsonValidationErrors('evidence_summary');
        $this->putJson("/api/convivencia/protocol-activation-parts/{$blockedRuntime['id']}", [
            'revision' => 1,
            'status' => 'completed',
            'notes' => 'Se intentó verificar.',
            'data' => ['condition_confirmed' => true],
        ])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->putJson("/api/convivencia/protocol-activation-parts/{$blockedRuntime['id']}", [
            'revision' => 1,
            'status' => 'not_applicable',
        ])->assertUnprocessable()->assertJsonValidationErrors('notes');
        $this->putJson("/api/convivencia/protocol-activation-parts/{$blockedRuntime['id']}", [
            'revision' => 1,
            'status' => 'not_applicable',
            'notes' => 'No procede sanción disciplinaria contra un párvulo.',
        ])->assertOk();

        $newDueAt = now()->addWeek()->toIso8601String();
        $this->putJson("/api/convivencia/protocol-activation-parts/{$evidenceRuntime['id']}", [
            'revision' => 2,
            'status' => 'in_progress',
            'due_at' => $newDueAt,
        ])->assertUnprocessable()->assertJsonValidationErrors('notes');
        $this->putJson("/api/convivencia/protocol-activation-parts/{$evidenceRuntime['id']}", [
            'revision' => 2,
            'status' => 'completed',
            'due_at' => $newDueAt,
            'notes' => 'Plazo ajustado por calendario institucional.',
            'evidence_summary' => 'Acta incorporada al expediente.',
        ])->assertOk();

        $advanced = $this->postJson("/api/convivencia/protocol-activation-steps/{$firstStepId}/complete", [
            'revision' => 3,
            'notes' => 'Etapa cerrada con respaldo completo.',
        ])->assertOk()
            ->assertJsonPath('data.progress.current_order', 2)
            ->assertJsonPath('data.progress.completed_steps', 1)
            ->assertJsonPath('data.progress.percentage', 50)
            ->json('data');
        $secondStepId = (int) collect($advanced['runtime_steps'])->firstWhere('step_order', 2)['id'];

        $closed = $this->postJson("/api/convivencia/protocol-activation-steps/{$secondStepId}/complete", [
            'revision' => 4,
            'notes' => 'Cierre final.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'cerrado')
            ->assertJsonPath('data.progress.percentage', 100)
            ->json('data');
        $this->assertNotNull($closed['closed_at']);

        $this->putJson("/api/convivencia/protocol-activation-parts/{$evidenceRuntime['id']}", [
            'revision' => 5,
            'status' => 'in_progress',
            'notes' => 'Intento de reapertura.',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->deleteJson("/api/convivencia/protocols/{$protocol['id']}")->assertOk();
        $this->getJson("/api/convivencia/protocol-activations/{$activation['id']}")
            ->assertOk()->assertJsonPath('data.protocol.name', 'Nombre modificado después');
    }

    public function test_activation_visibility_and_dashboard_filters_respect_case_privacy(): void
    {
        $superAdmin = $this->seedAndActAsSuperAdmin();
        $protocol = ConvivenciaProtocol::query()->where('status', 'activo')->firstOrFail();
        $sensitiveCase = ConvivenciaCase::query()->where('is_sensitive', true)->firstOrFail();
        $academicYearId = $sensitiveCase->academic_year_id;
        $privateActivation = $this->postJson('/api/convivencia/protocol-activations', [
            'protocol_id' => $protocol->id,
            'case_id' => $sensitiveCase->id,
        ])->assertCreated()->json('data.id');

        $visibleCase = ConvivenciaCase::query()->whereKeyNot($sensitiveCase->id)->firstOrFail();
        $visibleCase->forceFill(['is_sensitive' => false])->save();
        $visibleActivation = $this->postJson('/api/convivencia/protocol-activations', [
            'protocol_id' => $protocol->id,
            'case_id' => $visibleCase->id,
        ])->assertCreated()->json('data.id');
        $contextlessLegacyActivation = ConvivenciaProtocolActivation::query()->create([
            'protocol_id' => $protocol->id,
            'activated_by' => $superAdmin->id,
            'activated_at' => now()->subDay(),
            'status' => 'activo',
            'current_stage_name' => 'Activación legacy sin contexto',
            'revision' => 1,
        ]);
        $searchResults = $this->getJson('/api/convivencia/protocol-activations?search=legacy&per_page=15')
            ->assertOk();
        $this->assertSame(
            [$contextlessLegacyActivation->id],
            collect($searchResults->json('data'))->pluck('id')->all()
        );
        $complaint = ConvivenciaComplaint::query()->firstOrFail();
        $complaint->forceFill(['is_sensitive' => false])->save();
        $this->postJson('/api/convivencia/protocol-activations', [
            'protocol_id' => $protocol->id,
            'case_id' => $visibleCase->id,
            'complaint_id' => $complaint->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['case_id', 'complaint_id']);

        $this->getJson('/api/convivencia/dashboard?criticality_label=CRITICIDAD_QUE_NO_EXISTE')
            ->assertOk()->assertJsonPath('metrics.active_protocols', 0);

        $managerOnly = User::factory()->create(['active' => true]);
        $managerOnlyRole = Role::query()->create([
            'name' => 'Editor de protocolos sin permiso paraguas',
            'slug' => 'editor_protocolos_granular_test',
            'active' => true,
        ]);
        $managerOnlyRole->permissions()->attach(
            Permission::query()->where('slug', 'gestionar_protocolos_convivencia')->value('id')
        );
        $managerOnly->roles()->attach($managerOnlyRole);
        Sanctum::actingAs($managerOnly->fresh());
        $managerProtocols = $this->getJson('/api/convivencia/protocols?per_page=100')->assertOk();
        $managerProtocol = collect($managerProtocols->json('data'))->firstWhere('id', $protocol->id);
        $managerActivations = $this->getJson("/api/convivencia/protocol-activations?protocol_id={$protocol->id}&per_page=100")
            ->assertOk();
        $this->assertSame((int) $managerActivations->json('total'), (int) $managerProtocol['activations_count']);
        $managerActivationIds = collect($managerActivations->json('data'))->pluck('id');
        $this->assertTrue($managerActivationIds->contains($contextlessLegacyActivation->id));
        $this->getJson("/api/convivencia/protocol-activations/{$contextlessLegacyActivation->id}")->assertOk();
        $this->getJson('/api/convivencia/catalogs')
            ->assertOk()
            ->assertJsonCount(0, 'students')
            ->assertJsonCount(0, 'staff')
            ->assertJsonCount(0, 'users')
            ->assertJsonMissingPath('external_institutions.0.contact_name')
            ->assertJsonMissingPath('external_institutions.0.contact_email')
            ->assertJsonMissingPath('external_institutions.0.contact_phone')
            ->assertJsonPath('capabilities.can_manage_protocols', true)
            ->assertJsonPath('capabilities.can_activate_protocols', false);
        $this->getJson('/api/convivencia/students?search=estudiante')->assertForbidden();
        $this->postJson('/api/convivencia/protocol-activations', [
            'protocol_id' => $protocol->id,
            'case_id' => $visibleCase->id,
        ])->assertForbidden();

        Sanctum::actingAs($superAdmin);

        $limited = User::factory()->create(['active' => true]);
        $role = Role::query()->create([
            'name' => 'Gestor de protocolos limitado',
            'slug' => 'gestor_protocolos_limitado_test',
            'active' => true,
        ]);
        $role->permissions()->attach(Permission::query()->whereIn('slug', [
            'ver_convivencia',
            'ver_dashboard_convivencia',
            'ver_casos_convivencia',
            'activar_protocolos_convivencia',
        ])->pluck('id'));
        $limited->roles()->attach($role);
        Sanctum::actingAs($limited->fresh());

        $list = $this->getJson('/api/convivencia/protocol-activations?per_page=500')->assertOk();
        $ids = collect($list->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($visibleActivation));
        $this->assertTrue($ids->contains($contextlessLegacyActivation->id));
        $this->assertFalse($ids->contains($privateActivation));
        $this->getJson("/api/convivencia/protocol-activations/{$privateActivation}")->assertForbidden();

        $protocolDefinition = $this->getJson("/api/convivencia/protocols/{$protocol->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.activations')
            ->assertJsonMissingPath('data.protocol_snapshot')
            ->json('data');
        $this->assertLessThan(
            $protocol->activations()->count(),
            (int) $protocolDefinition['activations_count']
        );

        $this->getJson("/api/convivencia/dashboard?academic_year_id={$academicYearId}")
            ->assertOk()
            ->assertJsonStructure([
                'metrics' => [
                    'overdue_protocol_steps',
                    'due_soon_protocol_steps',
                    'protocol_compliance_percentage',
                    'average_protocol_closure_hours',
                ],
                'charts' => [
                    'activations_by_protocol',
                    'current_stage_distribution',
                    'parts_by_category',
                    'bottlenecks',
                ],
            ]);

        Sanctum::actingAs($superAdmin);
    }

    public function test_legacy_activation_get_is_read_only_and_explicit_materialization_is_audited(): void
    {
        $user = $this->seedAndActAsSuperAdmin();
        $protocol = ConvivenciaProtocol::query()
            ->where('status', 'activo')
            ->whereHas('steps', fn ($query) => $query->where('active', true))
            ->firstOrFail();
        $step = $protocol->steps()->where('active', true)->orderBy('step_order')->firstOrFail();
        $case = ConvivenciaCase::query()->firstOrFail();
        $legacy = ConvivenciaProtocolActivation::query()->create([
            'protocol_id' => $protocol->id,
            'case_id' => $case->id,
            'current_step_id' => $step->id,
            'activated_by' => $user->id,
            'activated_at' => now()->subDay(),
            'status' => 'activo',
            'current_stage_name' => $step->stage_name,
            'due_at' => now()->addDay(),
            'progress_percentage' => 0,
            'revision' => 1,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->getJson("/api/convivencia/protocol-activations/{$legacy->id}")
            ->assertOk()
            ->assertJsonPath('data.legacy_mode', true)
            ->assertJsonPath('data.runtime_materialized', false)
            ->assertJsonPath('data.steps.0.read_only', true)
            ->assertJsonCount(0, 'data.runtime_steps');
        $this->getJson("/api/convivencia/protocol-activations/{$legacy->id}/progress")
            ->assertOk()
            ->assertJsonPath('data.current_order', $step->step_order)
            ->assertJsonPath('data.total_steps', $protocol->steps()->where('active', true)->count());

        $this->assertSame(0, $legacy->runtimeSteps()->count());
        $this->assertSame(1, (int) $legacy->fresh()->revision);
        $this->assertDatabaseMissing('convivencia_status_logs', [
            'loggable_type' => ConvivenciaProtocolActivation::class,
            'loggable_id' => $legacy->id,
            'event_type' => 'runtime_materialized',
        ]);

        $materialized = $this->postJson("/api/convivencia/protocol-activations/{$legacy->id}/materialize-runtime", [
            'revision' => 1,
        ])->assertOk()
            ->assertJsonPath('data.legacy_mode', false)
            ->assertJsonPath('data.runtime_materialized', true)
            ->assertJsonPath('data.progress.current_order', $step->step_order)
            ->json('data');

        $this->assertNotEmpty($materialized['runtime_steps']);
        $this->assertSame(2, (int) $legacy->fresh()->revision);
        $this->assertDatabaseHas('convivencia_protocol_activation_logs', [
            'activation_id' => $legacy->id,
            'action_type' => 'materializacion_legacy',
        ]);
        $this->assertDatabaseHas('convivencia_status_logs', [
            'loggable_type' => ConvivenciaProtocolActivation::class,
            'loggable_id' => $legacy->id,
            'event_type' => 'runtime_materialized',
            'changed_by' => $user->id,
        ]);
    }

    public function test_completion_rules_accept_object_criteria_and_requires_all_parts(): void
    {
        $this->seedAndActAsSuperAdmin();
        $case = ConvivenciaCase::query()->firstOrFail();
        $optionalPartId = $this->createPart([
            'category' => 'follow_up',
            'code' => 'optional_all_parts',
            'title' => 'Seguimiento opcional que la regla exige resolver',
        ]);
        $protocol = $this->postJson('/api/convivencia/protocols', [
            'code' => 'TEST-COMPLETION-OBJECTS',
            'name' => 'Reglas de finalización editables',
            'status' => 'activo',
            'steps' => [[
                'step_order' => 1,
                'code' => 'verify',
                'stage_name' => 'Verificación',
                'deadline_value' => 10,
                'deadline_unit' => 'school_days',
                'can_extend' => true,
                'extension_value' => 5,
                'extension_unit' => 'school_days',
                'metadata' => [
                    'extension_requires_approval' => true,
                    'extension_requires_reason' => true,
                ],
                'completion_rule' => [
                    'requires_all_parts' => true,
                    'all' => [
                        'field' => 'evidence',
                        'operator' => 'present',
                        'label' => 'Evidencia presente',
                    ],
                    'any' => [
                        ['key' => 'notified', 'label' => 'Notificación realizada'],
                        ['code' => 'referred', 'label' => 'Derivación realizada'],
                    ],
                ],
                'part_links' => [[
                    'protocol_part_id' => $optionalPartId,
                    'is_required' => false,
                ]],
            ]],
        ])->assertCreated()->json('data');
        $activation = $this->postJson('/api/convivencia/protocol-activations', [
            'protocol_id' => $protocol['id'],
            'case_id' => $case->id,
        ])->assertCreated()->json('data');
        $step = $activation['runtime_steps'][0];
        $optionalPart = $step['parts'][0];

        $this->putJson("/api/convivencia/protocol-activation-steps/{$step['id']}", [
            'revision' => 1,
            'extension_value' => 365,
            'extension_unit' => 'calendar_days',
            'extension_approved' => true,
            'log_notes' => 'Intento fuera del máximo reglamentario.',
        ])->assertUnprocessable()->assertJsonValidationErrors('extension_value');
        $this->putJson("/api/convivencia/protocol-activation-steps/{$step['id']}", [
            'revision' => 1,
            'extension_value' => 5,
            'extension_unit' => 'school_days',
            'log_notes' => 'Prórroga fundada aún no autorizada.',
        ])->assertUnprocessable()->assertJsonValidationErrors('extension_approved');
        $this->putJson("/api/convivencia/protocol-activation-steps/{$step['id']}", [
            'revision' => 1,
            'extension_value' => 5,
            'extension_unit' => 'school_days',
            'extension_approved' => true,
            'log_notes' => 'Prórroga fundada y autorizada por la instancia responsable.',
        ])->assertOk()
            ->assertJsonPath('data.runtime_steps.0.data.last_extension.approval_confirmed', true)
            ->assertJsonPath('data.runtime_steps.0.data.extension_used_value', 5);
        $this->putJson("/api/convivencia/protocol-activation-steps/{$step['id']}", [
            'revision' => 2,
            'extension_value' => 5,
            'extension_unit' => 'school_days',
            'extension_approved' => true,
            'log_notes' => 'Segundo intento que excedería el máximo acumulado.',
            'data' => [
                'extension_used_value' => 0,
                'extensions' => [],
                'last_extension' => null,
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('extension_value');

        $this->postJson("/api/convivencia/protocol-activation-steps/{$step['id']}/complete", [
            'revision' => 2,
        ])->assertUnprocessable()->assertJsonValidationErrors('parts');
        $this->putJson("/api/convivencia/protocol-activation-parts/{$optionalPart['id']}", [
            'revision' => 2,
            'status' => 'not_applicable',
            'notes' => 'No se requiere en este caso; parte opcional resuelta explícitamente.',
        ])->assertOk();

        $this->postJson("/api/convivencia/protocol-activation-steps/{$step['id']}/complete", [
            'revision' => 3,
            'completion_criteria' => [
                ['key' => 'evidence', 'label' => 'Evidencia presente', 'completed' => true],
                ['key' => 'notified', 'label' => 'Notificación realizada', 'completed' => false],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('completion_criteria');

        $this->postJson("/api/convivencia/protocol-activation-steps/{$step['id']}/complete", [
            'revision' => 3,
            'completion_criteria' => [
                ['key' => 'evidence', 'label' => 'Evidencia presente', 'completed' => true],
                ['key' => 'notified', 'label' => 'Notificación realizada', 'completed' => true],
            ],
        ])->assertOk()
            ->assertJsonPath('data.status', 'cerrado')
            ->assertJsonPath('data.progress.percentage', 100);
    }

    public function test_conditional_part_cannot_become_not_applicable_with_a_stale_positive_confirmation(): void
    {
        $this->seedAndActAsSuperAdmin();
        $case = ConvivenciaCase::query()->firstOrFail();
        $conditionalPartId = $this->createPart([
            'category' => 'protective_measure',
            'code' => 'conditional_state_guard',
            'title' => 'Medida sujeta a una condición verificable',
        ]);
        $protocol = $this->postJson('/api/convivencia/protocols', [
            'code' => 'TEST-CONDITIONAL-STATE',
            'name' => 'Consistencia de partes condicionales',
            'status' => 'activo',
            'steps' => [[
                'step_order' => 1,
                'code' => 'conditional',
                'stage_name' => 'Evaluar condición',
                'part_links' => [[
                    'protocol_part_id' => $conditionalPartId,
                    'is_required' => true,
                    'condition' => [
                        'field' => 'repeat_event',
                        'operator' => 'equals',
                        'value' => true,
                    ],
                ]],
            ]],
        ])->assertCreated()->json('data');
        $activation = $this->postJson('/api/convivencia/protocol-activations', [
            'protocol_id' => $protocol['id'],
            'case_id' => $case->id,
        ])->assertCreated()->json('data');
        $step = $activation['runtime_steps'][0];
        $part = $step['parts'][0];

        $this->putJson("/api/convivencia/protocol-activation-parts/{$part['id']}", [
            'revision' => 1,
            'status' => 'in_progress',
            'data' => ['condition_confirmed' => true],
        ])->assertOk();
        $this->putJson("/api/convivencia/protocol-activation-parts/{$part['id']}", [
            'revision' => 2,
            'status' => 'not_applicable',
            'notes' => 'Intento contradictorio sin reevaluar la condición.',
        ])->assertUnprocessable()->assertJsonValidationErrors('data.condition_confirmed');
        $this->assertDatabaseHas('convivencia_protocol_activation_parts', [
            'id' => $part['id'],
            'status' => 'in_progress',
        ]);

        $this->putJson("/api/convivencia/protocol-activation-parts/{$part['id']}", [
            'revision' => 2,
            'status' => 'not_applicable',
            'notes' => 'La condición fue reevaluada y no se presenta en este caso.',
            'data' => ['condition_confirmed' => false],
        ])->assertOk();
        $this->postJson("/api/convivencia/protocol-activation-steps/{$step['id']}/complete", [
            'revision' => 3,
        ])->assertOk()->assertJsonPath('data.status', 'cerrado');
    }

    public function test_protocol_activation_attachments_are_private_and_enforce_context_access(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = $this->seedAndActAsSuperAdmin();
        $protocol = ConvivenciaProtocol::query()->where('status', 'activo')->firstOrFail();
        $visibleCase = ConvivenciaCase::query()->firstOrFail();
        $visibleCase->forceFill(['is_sensitive' => false])->save();
        $sensitiveCase = ConvivenciaCase::query()->whereKeyNot($visibleCase->id)->firstOrFail();
        $sensitiveCase->forceFill(['is_sensitive' => true])->save();
        $visibleActivation = $this->postJson('/api/convivencia/protocol-activations', [
            'protocol_id' => $protocol->id,
            'case_id' => $visibleCase->id,
        ])->assertCreated()->json('data.id');
        $sensitiveActivation = $this->postJson('/api/convivencia/protocol-activations', [
            'protocol_id' => $protocol->id,
            'case_id' => $sensitiveCase->id,
        ])->assertCreated()->json('data.id');

        $upload = $this->post("/api/convivencia/protocol-activations/{$sensitiveActivation}/attachments", [
            'category' => 'evidencia',
            'confidentiality_level' => 'confidencial',
            'document' => UploadedFile::fake()->create('evidencia sensible.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $attachmentId = (int) $upload['id'];
        $privatePath = $upload['file_path'];
        $this->assertStringStartsWith('convivencia-private/', $privatePath);
        Storage::disk('local')->assertExists($privatePath);
        Storage::disk('public')->assertMissing($privatePath);
        $downloadResponse = $this->get("/api/convivencia/attachments/{$attachmentId}/download")
            ->assertOk()
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $cacheControl = (string) $downloadResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);

        $visibleActivationAttachment = $this->post("/api/convivencia/protocol-activations/{$visibleActivation}/attachments", [
            'category' => 'evidencia',
            'confidentiality_level' => 'confidencial',
            'document' => UploadedFile::fake()->create('evidencia-visible-reservada.pdf', 5, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $visibleCaseAttachment = $this->post("/api/convivencia/cases/{$visibleCase->id}/attachments", [
            'category' => 'evidencia',
            'confidentiality_level' => 'confidencial',
            'document' => UploadedFile::fake()->create('antecedente-caso-reservado.pdf', 5, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');

        $this->app['auth']->forgetGuards();
        $publicResponse = $this->get('/storage/'.$privatePath);
        $this->assertNotSame(
            Storage::disk('local')->get($privatePath),
            $publicResponse->getContent(),
            'La ruta pública nunca debe entregar el contenido del adjunto privado.'
        );
        $this->assertNotSame('application/pdf', $publicResponse->headers->get('Content-Type'));
        Sanctum::actingAs($admin);

        $this->post("/api/convivencia/protocol-activations/{$visibleActivation}/attachments", [
            'category' => 'evidencia',
            'document' => UploadedFile::fake()->create('archivo-ejecutable.exe', 5, 'application/octet-stream'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('document');
        $this->post("/api/convivencia/protocol-activations/{$visibleActivation}/attachments", [
            'category' => 'evidencia',
            'confidentiality_level' => 'publico_sin_control',
            'document' => UploadedFile::fake()->create('nivel-invalido.pdf', 5, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('confidentiality_level');

        $viewOnly = User::factory()->create(['active' => true]);
        $viewRole = Role::query()->create([
            'name' => 'Visor de convivencia sin activación',
            'slug' => 'visor_adjuntos_convivencia_test',
            'active' => true,
        ]);
        $viewRole->permissions()->attach(Permission::query()->whereIn('slug', [
            'ver_convivencia',
            'ver_casos_convivencia',
        ])->pluck('id'));
        $viewOnly->roles()->attach($viewRole);
        Sanctum::actingAs($viewOnly->fresh());
        $this->get("/api/convivencia/attachments/{$visibleActivationAttachment['id']}/download")
            ->assertForbidden();
        $caseAttachments = $this->getJson("/api/convivencia/cases/{$visibleCase->id}")
            ->assertOk()
            ->json('data.attachments');
        $this->assertNotContains((int) $visibleCaseAttachment['id'], collect($caseAttachments)->pluck('id')->all());
        $this->post("/api/convivencia/protocol-activations/{$visibleActivation}/attachments", [
            'category' => 'evidencia',
            'document' => UploadedFile::fake()->create('solo-lectura.pdf', 5, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertForbidden();

        $activator = User::factory()->create(['active' => true]);
        $activatorRole = Role::query()->create([
            'name' => 'Activador sin acceso sensible',
            'slug' => 'activador_adjuntos_sin_visibilidad_test',
            'active' => true,
        ]);
        $activatorRole->permissions()->attach(
            Permission::query()->where('slug', 'activar_protocolos_convivencia')->value('id')
        );
        $activator->roles()->attach($activatorRole);
        Sanctum::actingAs($activator->fresh());
        $this->get("/api/convivencia/attachments/{$attachmentId}/download")->assertForbidden();
        $this->deleteJson("/api/convivencia/attachments/{$attachmentId}")->assertForbidden();
        Storage::disk('local')->assertExists($privatePath);

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/convivencia/attachments/{$attachmentId}")->assertOk();
        Storage::disk('local')->assertMissing($privatePath);

        $legacyPath = 'convivencia/legacy/archivo.pdf';
        Storage::disk('public')->put($legacyPath, 'contenido legado');
        $legacyAttachment = ConvivenciaProtocolActivation::query()
            ->findOrFail($sensitiveActivation)
            ->attachments()
            ->create([
                'case_id' => $sensitiveCase->id,
                'category' => 'evidencia',
                'confidentiality_level' => 'confidencial',
                'is_sensitive' => true,
                'file_path' => $legacyPath,
                'original_name' => 'archivo-legado.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => 16,
                'uploaded_by' => $admin->id,
            ]);
        $this->get("/api/convivencia/attachments/{$legacyAttachment->id}/download")->assertOk();
        $this->deleteJson("/api/convivencia/attachments/{$legacyAttachment->id}")->assertOk();
        Storage::disk('public')->assertMissing($legacyPath);
    }

    private function createPart(array $attributes): int
    {
        return (int) $this->postJson('/api/convivencia/protocol-parts', array_merge([
            'category' => 'other',
            'title' => 'Parte de prueba',
            'active' => true,
        ], $attributes))->assertCreated()->json('data.id');
    }

    private function seedAndActAsSuperAdmin(): User
    {
        $this->seed(ConvivenciaSeeder::class);
        $user = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin'))->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }
}
