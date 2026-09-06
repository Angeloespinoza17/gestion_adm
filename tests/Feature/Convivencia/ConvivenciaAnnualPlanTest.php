<?php

namespace Tests\Feature\Convivencia;

use App\Models\Convivencia\ConvivenciaAttachment;
use App\Models\Convivencia\ConvivenciaCatalogItem;
use App\Models\Convivencia\ConvivenciaPlan;
use App\Models\Convivencia\ConvivenciaPlanAction;
use App\Models\Convivencia\ConvivenciaPlanActivity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Convivencia\ConvivenciaAccessService;
use Database\Seeders\ConvivenciaPlanProgressionSeeder;
use Database\Seeders\ConvivenciaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConvivenciaAnnualPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_document_is_preloaded_once_without_overwriting_other_plans(): void
    {
        $this->seed(ConvivenciaSeeder::class);
        $legacyPlanIds = ConvivenciaPlan::query()->pluck('id')->all();

        $this->artisan('convivencia:preload-plan-2026')->assertSuccessful();

        $plan = ConvivenciaPlan::query()->where('calendar_year', 2026)->firstOrFail();
        $this->assertSame('37a824f59b19cf1d1671b509de02f168188bf3bf9ffb0c120db69c2f1b0c0a33', $plan->source_document_sha256);
        $this->assertSame('borrador', $plan->status);
        $this->assertTrue($plan->regulatory_review_required);
        $this->assertCount(3, $plan->institutional_protocol);
        $this->assertCount(4, $plan->evaluation_indicators);
        $this->assertSame(10, $plan->actions()->count());
        $this->assertEquals(100.0, (float) $plan->actions()->sum('weight_percent'));
        $this->assertSame(14, ConvivenciaCatalogItem::query()->where('group', 'plan_activity_type')->count());
        $this->assertSame(1, $plan->versions()->count());
        $this->assertEqualsCanonicalizing($legacyPlanIds, ConvivenciaPlan::query()->whereNull('calendar_year')->pluck('id')->all());

        $fingerprint = [
            ...$plan->fresh()->only(['id', 'revision', 'version_number']),
            'updated_at' => $plan->fresh()->updated_at?->toISOString(),
        ];
        $this->artisan('convivencia:preload-plan-2026')->assertSuccessful();
        $this->assertSame($fingerprint, [
            ...$plan->fresh()->only(['id', 'revision', 'version_number']),
            'updated_at' => $plan->fresh()->updated_at?->toISOString(),
        ]);
        $this->assertSame(10, $plan->actions()->count());
        $this->assertSame(1, $plan->versions()->count());
    }

    public function test_progression_seeder_upgrades_only_the_pristine_preloaded_plan_and_is_idempotent(): void
    {
        $this->seed(ConvivenciaSeeder::class);
        $this->artisan('convivencia:preload-plan-2026')->assertSuccessful();
        $plan = ConvivenciaPlan::query()->where('calendar_year', 2026)->firstOrFail();
        $plan->actions()->update(['weight_percent' => 0]);
        $beforeVersion = $plan->version_number;

        $this->seed(ConvivenciaPlanProgressionSeeder::class);
        $plan->refresh();
        $this->assertEquals(100.0, (float) $plan->actions()->sum('weight_percent'));
        $this->assertSame($beforeVersion + 1, $plan->version_number);
        $fingerprint = [
            ...$plan->only(['revision', 'version_number']),
            'updated_at' => $plan->updated_at?->toISOString(),
        ];

        $this->seed(ConvivenciaPlanProgressionSeeder::class);
        $plan->refresh();
        $this->assertSame($fingerprint, [
            ...$plan->only(['revision', 'version_number']),
            'updated_at' => $plan->updated_at?->toISOString(),
        ]);
        $this->assertSame(14, ConvivenciaCatalogItem::query()->where('group', 'plan_activity_type')->count());
    }

    public function test_reference_word_file_is_hash_validated_stored_privately_and_not_duplicated(): void
    {
        Storage::fake('local');
        $this->seed(ConvivenciaSeeder::class);
        $source = UploadedFile::fake()->createWithContent('plan-convivencia-2026.docx', 'contenido-word-validado');
        config([
            'convivencia_plan_2026.source_document_name' => 'plan-convivencia-2026.docx',
            'convivencia_plan_2026.source_document_sha256' => hash_file('sha256', $source->getPathname()),
        ]);

        $this->artisan('convivencia:preload-plan-2026', ['--source' => $source->getPathname()])
            ->assertSuccessful();

        $plan = ConvivenciaPlan::query()->where('calendar_year', 2026)->firstOrFail();
        $attachment = $plan->attachments()->firstOrFail();
        $this->assertSame('plan-convivencia-2026.docx', $attachment->original_name);
        $this->assertSame('interna', $attachment->confidentiality_level);
        Storage::disk('local')->assertExists($attachment->file_path);

        $this->artisan('convivencia:preload-plan-2026', ['--source' => $source->getPathname()])
            ->assertSuccessful();
        $this->assertSame(1, $plan->attachments()->count());
    }

    public function test_plan_definitions_are_versioned_and_a_previous_version_can_be_restored(): void
    {
        [$user, $plan] = $this->preloadedPlan();

        $this->getJson('/api/convivencia/annual-plans/workspace?year=2026')
            ->assertOk()
            ->assertJsonPath('data.version_number', 1)
            ->assertJsonCount(10, 'data.actions')
            ->assertJsonPath('data.evaluation_indicators.0.title', 'Resolución alternativa');

        $this->postJson("/api/convivencia/plans/{$plan->id}/actions", [
            'plan_revision' => $plan->revision,
            'action_type' => 'formativa',
            'title' => 'Acción incorporada desde la interfaz',
            'target_audience' => 'Familias',
            'planned_month' => 6,
            'date_precision' => 'month',
            'status' => 'planificada',
            'change_summary' => 'Se agregó una acción de prueba.',
        ])->assertCreated()
            ->assertJsonPath('plan.version_number', 2);

        $this->postJson("/api/convivencia/plans/{$plan->id}/actions", [
            'plan_revision' => 1,
            'action_type' => 'formativa',
            'title' => 'Acción obsoleta',
            'planned_month' => 6,
            'date_precision' => 'month',
            'status' => 'planificada',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('plan_revision');

        $plan->refresh();
        $this->putJson("/api/convivencia/plans/{$plan->id}", [
            'revision' => $plan->revision,
            'calendar_year' => 2026,
            'name' => 'Plan 2026 actualizado',
            'general_objective' => $plan->general_objective,
            'status' => $plan->status,
            'change_summary' => 'Se ajustó el nombre institucional.',
        ])->assertOk()
            ->assertJsonPath('data.version_number', 3);

        $this->putJson("/api/convivencia/plans/{$plan->id}", [
            'revision' => 1,
            'calendar_year' => 2026,
            'name' => 'Cambio obsoleto',
            'general_objective' => $plan->general_objective,
            'status' => $plan->status,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('revision');

        $plan->refresh();
        $versionOne = $plan->versions()->where('version_number', 1)->firstOrFail();
        $this->postJson("/api/convivencia/plans/{$plan->id}/versions/{$versionOne->id}/restore", [
            'revision' => $plan->revision,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Plan de Gestión de la Convivencia Escolar 2026')
            ->assertJsonPath('data.version_number', 4)
            ->assertJsonCount(10, 'data.actions');

        $this->assertSame(4, $plan->fresh()->versions()->count());
    }

    public function test_plan_cannot_be_published_before_regulatory_review_or_moved_to_another_year(): void
    {
        [$user, $plan] = $this->preloadedPlan();
        $basePayload = [
            'revision' => $plan->revision,
            'calendar_year' => 2026,
            'name' => $plan->name,
            'general_objective' => $plan->general_objective,
            'status' => 'vigente',
        ];

        $this->putJson("/api/convivencia/plans/{$plan->id}", $basePayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->putJson("/api/convivencia/plans/{$plan->id}", [
            ...$basePayload,
            'regulatory_review_required' => false,
            'change_summary' => 'Cláusula revisada y plan aprobado.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'vigente');

        $plan->refresh();
        $this->assertNotNull($plan->approved_at);
        $this->assertSame($user->id, $plan->approved_by);

        $this->putJson("/api/convivencia/plans/{$plan->id}", [
            'revision' => $plan->revision,
            'calendar_year' => 2027,
            'name' => $plan->name,
            'general_objective' => $plan->general_objective,
            'status' => $plan->status,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('calendar_year');
    }

    public function test_activities_drive_progress_appear_in_calendar_and_accept_private_evidence(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        [$user, $plan] = $this->preloadedPlan();
        $action = $plan->actions()->firstOrFail();
        $activityType = ConvivenciaCatalogItem::query()
            ->where('group', 'plan_activity_type')
            ->where('code', 'jornada')
            ->firstOrFail();

        $activityId = $this->postJson("/api/convivencia/plan-actions/{$action->id}/activities", [
            'title' => 'Ejecución de la jornada de acogida',
            'activity_type_item_id' => $activityType->id,
            'starts_at' => '2026-03-03 09:00:00',
            'ends_at' => '2026-03-03 11:00:00',
            'status' => 'realizada',
            'contribution_percent' => 60,
            'completion_percent' => 10,
            'location' => 'Salón principal',
            'target_audience' => 'Estudiantes nuevas y familias',
            'attendee_count' => 45,
            'results' => 'Jornada ejecutada según lo planificado.',
        ])->assertCreated()
            ->assertJsonPath('data.completion_percent', 100)
            ->json('data.id');

        $activity = ConvivenciaPlanActivity::query()->findOrFail($activityId);
        $this->putJson("/api/convivencia/plan-activities/{$activityId}", [
            'revision' => $activity->revision,
            'title' => $activity->title,
            'activity_type_item_id' => $activityType->id,
            'starts_at' => $activity->starts_at->toDateTimeString(),
            'status' => 'en_ejecucion',
            'contribution_percent' => 60,
            'completion_percent' => 80,
        ])->assertOk()
            ->assertJsonPath('data.revision', 2);

        $this->putJson("/api/convivencia/plan-activities/{$activityId}", [
            'revision' => 1,
            'title' => 'Cambio obsoleto',
            'activity_type_item_id' => $activityType->id,
            'starts_at' => $activity->starts_at->toDateTimeString(),
            'status' => 'en_ejecucion',
            'contribution_percent' => 60,
            'completion_percent' => 90,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('revision');

        $this->assertSame('48.00', $action->fresh()->advance_percentage);
        $this->postJson("/api/convivencia/plan-actions/{$action->id}/activities", [
            'title' => 'Actividad que excede el aporte',
            'activity_type_item_id' => $activityType->id,
            'starts_at' => '2026-03-04 09:00:00',
            'status' => 'programada',
            'contribution_percent' => 50,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('contribution_percent');

        $this->getJson("/api/convivencia/plans/{$plan->id}/calendar?start=2026-03-01&end=2026-04-01")
            ->assertOk()
            ->assertJsonFragment(['id' => "action-{$action->id}"])
            ->assertJsonFragment(['id' => "activity-{$activityId}"]);

        $decemberAction = $plan->actions()->where('planned_month', 12)->firstOrFail();
        $this->getJson("/api/convivencia/plans/{$plan->id}/calendar?start=2026-12-28&end=2027-02-08")
            ->assertOk()
            ->assertJsonFragment(['id' => "action-{$decemberAction->id}"])
            ->assertJsonMissing(['id' => "action-{$action->id}"]);

        $this->post("/api/convivencia/plan-activities/{$activityId}/attachments", [
            'category' => 'evidencia',
            'confidentiality_level' => 'general',
            'document' => UploadedFile::fake()->create('acta-acogida.pdf', 80, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $attachment = ConvivenciaAttachment::query()->where('attachable_type', ConvivenciaPlanActivity::class)->firstOrFail();
        Storage::disk('local')->assertExists($attachment->file_path);
        Storage::disk('public')->assertMissing($attachment->file_path);
        $this->get("/api/convivencia/attachments/{$attachment->id}/download")->assertOk();
    }

    public function test_actions_can_span_years_and_typed_activities_drive_weighted_plan_progress(): void
    {
        [, $plan] = $this->preloadedPlan();
        $charla = ConvivenciaCatalogItem::query()->where('group', 'plan_activity_type')->where('code', 'charla')->firstOrFail();
        $wrongType = ConvivenciaCatalogItem::query()->where('group', 'plan_dimension')->firstOrFail();
        $first = $plan->actions()->orderBy('sort_order')->firstOrFail();
        $second = $plan->actions()->orderBy('sort_order')->skip(1)->firstOrFail();

        $this->postJson("/api/convivencia/plan-actions/{$first->id}/activities", [
            'activity_type_item_id' => $charla->id,
            'title' => 'Charla de acogida',
            'starts_at' => '2026-03-05 09:00:00',
            'status' => 'en_ejecucion',
            'contribution_percent' => 50,
            'completion_percent' => 50,
        ])->assertCreated()
            ->assertJsonPath('data.activity_type_label', 'Charla');

        $this->postJson("/api/convivencia/plan-actions/{$second->id}/activities", [
            'activity_type_item_id' => $charla->id,
            'title' => 'Charla de buen trato',
            'starts_at' => '2026-04-05 09:00:00',
            'status' => 'realizada',
            'contribution_percent' => 40,
            'completion_percent' => 5,
        ])->assertCreated();

        $this->assertSame('25.00', $first->fresh()->advance_percentage);
        $this->assertSame('40.00', $second->fresh()->advance_percentage);
        $this->assertSame('6.50', $plan->fresh()->advance_percentage);
        $this->getJson("/api/convivencia/plan-actions/{$first->id}")
            ->assertOk()
            ->assertJsonPath('data.activities.0.activity_type.name', 'Charla');

        $this->postJson("/api/convivencia/plan-actions/{$first->id}/activities", [
            'activity_type_item_id' => $wrongType->id,
            'title' => 'Tipo de catálogo incorrecto',
            'starts_at' => '2026-03-06 09:00:00',
            'status' => 'programada',
            'contribution_percent' => 10,
        ])->assertUnprocessable()->assertJsonValidationErrors('activity_type_item_id');

        $multiyearId = $this->postJson("/api/convivencia/plans/{$plan->id}/actions", [
            'plan_revision' => $plan->revision,
            'action_type' => 'preventiva',
            'title' => 'Acompañamiento institucional de largo plazo',
            'date_precision' => 'exact',
            'starts_on' => '2026-10-01',
            'ends_on' => '2028-03-31',
            'weight_percent' => 0,
            'status' => 'planificada',
            'change_summary' => 'Se incorpora una acción multianual sin alterar la ponderación.',
        ])->assertCreated()->assertJsonPath('data.is_multi_year', true)->json('data.id');

        $this->getJson("/api/convivencia/plans/{$plan->id}/calendar?start=2027-01-01&end=2028-01-01")
            ->assertOk()
            ->assertJsonFragment(['id' => "action-{$multiyearId}"]);

        $first->refresh();
        $this->putJson("/api/convivencia/plan-actions/{$first->id}", [
            'plan_revision' => $plan->fresh()->revision,
            'action_type' => $first->action_type,
            'title' => $first->title,
            'date_precision' => $first->date_precision,
            'planned_month' => $first->planned_month,
            'status' => $first->status,
            'weight_percent' => 20,
            'change_summary' => 'Intento de sobreponderación.',
        ])->assertUnprocessable()->assertJsonValidationErrors('weight_percent');
    }

    public function test_a_new_year_plan_can_be_created_from_the_previous_version_without_copying_execution(): void
    {
        [, $plan] = $this->preloadedPlan();
        $multiyear = $plan->actions()->create([
            'action_type' => 'formativa',
            'title' => 'Programa plurianual',
            'date_precision' => 'exact',
            'starts_on' => '2026-08-01',
            'ends_on' => '2028-07-31',
            'weight_percent' => 0,
            'status' => 'planificada',
            'sort_order' => 11,
        ]);
        $type = ConvivenciaCatalogItem::query()->where('group', 'plan_activity_type')->firstOrFail();
        ConvivenciaPlanActivity::query()->create([
            'plan_action_id' => $multiyear->id,
            'activity_type_item_id' => $type->id,
            'activity_type_label' => $type->name,
            'title' => 'Ejecución que no debe duplicarse',
            'starts_at' => '2026-08-10 09:00:00',
            'status' => 'realizada',
            'contribution_percent' => 100,
            'completion_percent' => 100,
            'revision' => 1,
        ]);

        $this->postJson("/api/convivencia/plans/{$plan->id}/clone-to-year", [
            'revision' => $plan->revision,
            'target_year' => 2027,
            'academic_year_id' => $plan->academic_year_id,
        ])->assertUnprocessable()->assertJsonValidationErrors('academic_year_id');

        $newPlanId = $this->postJson("/api/convivencia/plans/{$plan->id}/clone-to-year", [
            'revision' => $plan->revision,
            'target_year' => 2027,
            'academic_year_id' => null,
        ])->assertCreated()
            ->assertJsonPath('data.calendar_year', 2027)
            ->assertJsonPath('data.previous_plan_id', $plan->id)
            ->assertJsonPath('data.version_number', 1)
            ->json('data.id');

        $newPlan = ConvivenciaPlan::query()->findOrFail($newPlanId);
        $this->assertSame(11, $newPlan->actions()->count());
        $this->assertSame(0, ConvivenciaPlanActivity::query()->whereHas('action', fn ($query) => $query->where('plan_id', $newPlan->id))->count());
        $this->assertEquals(100.0, (float) $newPlan->actions()->sum('weight_percent'));
        $shifted = ConvivenciaPlanAction::query()->where('plan_id', $newPlan->id)->where('title', 'Programa plurianual')->firstOrFail();
        $this->assertSame('2027-08-01', $shifted->starts_on->toDateString());
        $this->assertSame('2029-07-31', $shifted->ends_on->toDateString());

        $this->postJson("/api/convivencia/plans/{$plan->id}/clone-to-year", [
            'revision' => $plan->revision,
            'target_year' => 2027,
        ])->assertUnprocessable()->assertJsonValidationErrors('target_year');
    }

    public function test_plan_workspace_mutations_and_export_respect_granular_permissions(): void
    {
        [, $plan] = $this->preloadedPlan();
        $viewer = $this->userWithPermissions([ConvivenciaAccessService::VIEW_CASES_PERMISSION], 'visor_plan_anual');
        Sanctum::actingAs($viewer);

        $this->getJson('/api/convivencia/annual-plans/workspace?year=2026')
            ->assertOk()
            ->assertJsonPath('data.id', $plan->id)
            ->assertJsonPath('capabilities.can_manage', false)
            ->assertJsonPath('capabilities.can_export', false);
        $this->postJson("/api/convivencia/plans/{$plan->id}/actions", [
            'plan_revision' => $plan->revision,
            'action_type' => 'formativa',
            'title' => 'Intento sin autorización',
            'planned_month' => 5,
            'date_precision' => 'month',
            'status' => 'planificada',
        ])->assertForbidden();
        $this->getJson("/api/convivencia/plans/{$plan->id}/export-data")->assertForbidden();

        $exporter = $this->userWithPermissions([
            ConvivenciaAccessService::VIEW_CASES_PERMISSION,
            ConvivenciaAccessService::EXPORT_REPORTS_PERMISSION,
        ], 'exportador_plan_anual');
        Sanctum::actingAs($exporter);
        $this->getJson("/api/convivencia/plans/{$plan->id}/export-data")
            ->assertOk()
            ->assertJsonPath('data.id', $plan->id)
            ->assertJsonCount(10, 'data.actions');
    }

    private function preloadedPlan(): array
    {
        $this->seed(ConvivenciaSeeder::class);
        $this->artisan('convivencia:preload-plan-2026')->assertSuccessful();
        $user = User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin'))->firstOrFail();
        $this->actingAs($user, 'sanctum');

        return [$user, ConvivenciaPlan::query()->where('calendar_year', 2026)->firstOrFail()];
    }

    private function userWithPermissions(array $slugs, string $roleSlug): User
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->create([
            'name' => str_replace('_', ' ', ucfirst($roleSlug)),
            'slug' => $roleSlug,
            'active' => true,
        ]);
        $permissions = Permission::query()->whereIn('slug', $slugs)->pluck('id');
        $this->assertCount(count($slugs), $permissions);
        $role->permissions()->attach($permissions);
        $user->roles()->attach($role);

        return $user->fresh();
    }
}
