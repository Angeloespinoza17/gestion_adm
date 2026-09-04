<?php

namespace Tests\Feature\Orientation;

use App\Models\Orientation\OrientationAction;
use App\Models\Orientation\OrientationActivity;
use App\Models\Orientation\OrientationCalendarizationEntry;
use App\Models\Orientation\OrientationEvidence;
use App\Models\Orientation\OrientationPlan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrientationAnnualPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_orientation_role_and_navigation_are_registered_additively(): void
    {
        $role = Role::query()->where('slug', 'orientacion')->firstOrFail();

        $this->assertSame('Orientador/a', $role->name);
        $this->assertEqualsCanonicalizing([
            'orientation.view',
            'orientation.manage_plan',
            'orientation.manage_execution',
            'orientation.manage_evidence',
        ], $role->permissions()->where('permissions.slug', 'like', 'orientation.%')->pluck('slug')->all());
        $this->assertEqualsCanonicalizing([
            'orientation',
            'orientation_annual_plan',
            'orientation_calendarization',
            'orientation_calendar',
            'orientation_statistics',
        ], $role->modules()->whereIn('system_modules.slug', [
            'orientation',
            'orientation_annual_plan',
            'orientation_calendarization',
            'orientation_calendar',
            'orientation_statistics',
        ])->pluck('slug')->all());

        $this->assertSame('/orientation/plan-anual', SystemModule::query()->where('slug', 'orientation_annual_plan')->value('frontend_route'));
        $this->assertSame('/orientation/calendarizacion', SystemModule::query()->where('slug', 'orientation_calendarization')->value('frontend_route'));
        $this->assertSame('/orientation/estadisticas', SystemModule::query()->where('slug', 'orientation_statistics')->value('frontend_route'));
    }

    public function test_super_admin_receives_all_orientation_permissions_and_navigation(): void
    {
        $role = Role::query()->where('slug', 'super_admin')->firstOrFail();

        $this->assertEqualsCanonicalizing([
            'orientation.view',
            'orientation.manage_plan',
            'orientation.manage_execution',
            'orientation.manage_evidence',
        ], $role->permissions()->where('permissions.slug', 'like', 'orientation.%')->pluck('slug')->all());

        $this->assertEqualsCanonicalizing([
            'orientation',
            'orientation_annual_plan',
            'orientation_calendarization',
            'orientation_calendar',
            'orientation_statistics',
        ], $role->modules()->whereIn('system_modules.slug', [
            'orientation',
            'orientation_annual_plan',
            'orientation_calendarization',
            'orientation_calendar',
            'orientation_statistics',
        ])->pluck('slug')->all());
    }

    public function test_only_one_orientation_plan_can_be_created_per_calendar_year(): void
    {
        $user = $this->orientationUser();
        $payload = [
            'year' => 2026,
            'title' => 'Plan Anual de Orientación 2026',
            'general_objective' => 'Fortalecer la formación integral.',
            'description' => 'Plan institucional del año.',
            'status' => 'active',
            'owner_user_id' => $user->id,
        ];

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/orientation/plans', $payload)
            ->assertCreated()
            ->assertJsonPath('data.year', 2026);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/orientation/plans', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('year');

        $plan = OrientationPlan::query()->where('year', 2026)->firstOrFail();
        OrientationAction::query()->create([
            'orientation_plan_id' => $plan->id,
            'title' => 'Acción que fija el contexto anual',
            'status' => 'planned',
            'progress' => 0,
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/orientation/plans/{$plan->id}", [
                ...$payload,
                'year' => 2027,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('year');

        $this->assertSame(1, OrientationPlan::query()->where('year', 2026)->count());
    }

    public function test_calendarization_exposes_four_layers_and_imports_reference_without_duplicates(): void
    {
        $user = $this->orientationUser();
        $plan = OrientationPlan::query()->create([
            'year' => 2026,
            'title' => 'Plan Anual de Orientación 2026',
            'status' => 'active',
            'created_by' => $user->id,
        ]);
        $existingAction = OrientationAction::query()->create([
            'orientation_plan_id' => $plan->id,
            'title' => 'Acción existente que no debe recibir vínculos automáticos',
            'status' => 'in_progress',
            'progress' => 37,
        ]);
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/orientation/calendarization?year=2026')
            ->assertOk()
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private')
            ->assertJsonPath('plan.id', $plan->id)
            ->assertJsonPath('is_reference_preview', true)
            ->assertJsonCount(4, 'layers')
            ->assertJsonCount(164, 'entries')
            ->assertJsonPath('summary.by_layer.primary', 42)
            ->assertJsonPath('summary.by_layer.secondary', 42)
            ->assertJsonPath('summary.by_layer.third', 42)
            ->assertJsonPath('summary.by_layer.fourth', 38)
            ->assertJsonPath('entries.0.start_date', '2026-03-02');

        $this->postJson("/api/orientation/plans/{$plan->id}/calendarization/import-reference")
            ->assertOk()
            ->assertJsonPath('imported', 164)
            ->assertJsonPath('total_reference_entries', 164);
        $this->assertDatabaseCount('orientation_calendarization_entries', 164);
        $this->assertSame(0, OrientationCalendarizationEntry::query()->whereNotNull('orientation_action_id')->count());
        $this->assertSame(37, $existingAction->fresh()->progress);

        $this->postJson("/api/orientation/plans/{$plan->id}/calendarization/import-reference")
            ->assertOk()
            ->assertJsonPath('imported', 0)
            ->assertJsonPath('total_reference_entries', 164);
        $this->assertDatabaseCount('orientation_calendarization_entries', 164);

        $this->getJson('/api/orientation/calendarization?year=2026')
            ->assertOk()
            ->assertJsonPath('is_reference_preview', false)
            ->assertJsonCount(164, 'entries');
    }

    public function test_calendarization_activity_can_be_linked_to_an_action_edited_and_deleted(): void
    {
        $user = $this->orientationUser();
        $plan = OrientationPlan::query()->create([
            'year' => 2027,
            'title' => 'Plan Anual de Orientación 2027',
            'status' => 'active',
            'created_by' => $user->id,
        ]);
        $action = OrientationAction::query()->create([
            'orientation_plan_id' => $plan->id,
            'title' => 'Acompañamiento vocacional',
            'status' => 'planned',
            'progress' => 0,
        ]);
        $this->actingAs($user, 'sanctum');

        $entryId = $this->postJson("/api/orientation/plans/{$plan->id}/calendarization", [
            'orientation_action_id' => $action->id,
            'level_group' => 'fourth',
            'title' => 'Taller de proyecto de vida',
            'description' => 'Trabajo guiado con IV° medio.',
            'category' => 'vocational',
            'start_date' => '2027-04-05',
            'end_date' => '2027-04-09',
            'status' => 'confirmed',
        ])->assertCreated()
            ->assertJsonPath('data.action.title', 'Acompañamiento vocacional')
            ->json('data.id');

        $this->putJson("/api/orientation/calendarization/{$entryId}", [
            'orientation_action_id' => $action->id,
            'level_group' => 'fourth',
            'title' => 'Taller de proyecto de vida actualizado',
            'description' => 'Trabajo guiado con IV° medio.',
            'category' => 'vocational',
            'start_date' => '2027-04-05',
            'end_date' => '2027-04-09',
            'status' => 'completed',
        ])->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->deleteJson("/api/orientation/calendarization/{$entryId}")->assertOk();
        $this->assertDatabaseMissing('orientation_calendarization_entries', ['id' => $entryId]);
        $this->assertSame(0, OrientationCalendarizationEntry::query()->count());
    }

    public function test_plan_actions_related_plans_activities_evidence_and_calendar_share_one_workflow(): void
    {
        Storage::fake('local');
        $user = $this->orientationUser();
        $this->actingAs($user, 'sanctum');

        $planId = $this->postJson('/api/orientation/plans', [
            'year' => 2026,
            'title' => 'Plan Anual de Orientación 2026',
            'general_objective' => 'Acompañar trayectorias formativas.',
            'status' => 'active',
            'owner_user_id' => $user->id,
        ])->assertCreated()->json('data.id');

        $relatedPlanId = $this->postJson("/api/orientation/plans/{$planId}/related-plans", [
            'name' => 'Plan de Afectividad, Sexualidad y Género',
            'category' => 'institutional',
            'description' => 'Instrumento transversal.',
            'reference_url' => 'https://example.test/plan-afectividad',
            'status' => 'active',
        ])->assertCreated()->json('data.id');

        $actionId = $this->postJson("/api/orientation/plans/{$planId}/actions", [
            'title' => 'Talleres focalizados de afectividad',
            'objective' => 'Promover el desarrollo afectivo responsable.',
            'target_levels' => '3° a 8° básico',
            'planned_verification_means' => "Lista de asistencia\nRegistro fotográfico",
            'material_resources' => 'Material de apoyo',
            'responsible_summary' => 'Dupla Psicosocial',
            'start_date' => '2026-04-06',
            'end_date' => '2026-11-30',
            'status' => 'in_progress',
            'progress' => 35,
            'responsible_user_ids' => [$user->id],
            'related_plan_ids' => [$relatedPlanId],
        ])->assertCreated()->json('data.id');

        $activityId = $this->postJson("/api/orientation/actions/{$actionId}/activities", [
            'title' => 'Taller de salud menstrual',
            'description' => 'Primera jornada del ciclo.',
            'starts_at' => '2026-05-12 10:00:00',
            'ends_at' => '2026-05-12 11:30:00',
            'status' => 'completed',
            'contribution_percent' => 35,
            'completion_percent' => 100,
            'location' => 'Sala multiuso',
            'participants' => '6° y 7° básico',
            'attendee_count' => 54,
            'results' => 'Actividad realizada según planificación.',
        ])->assertCreated()
            ->assertJsonPath('data.progress', 35)
            ->assertJsonPath('data.activity_contribution.allocated', 35)
            ->assertJsonPath('data.activity_contribution.earned', 35)
            ->json('activity_id');

        $evidenceResponse = $this->post("/api/orientation/actions/{$actionId}/evidences", [
            'title' => 'Lista de asistencia taller',
            'evidence_type' => 'attendance',
            'description' => 'Respaldo firmado de la jornada.',
            'occurred_on' => '2026-05-12',
            'orientation_activity_id' => $activityId,
            'file' => UploadedFile::fake()->create('asistencia.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json']);
        $evidenceResponse->assertCreated()->assertJsonPath('data.has_file', true);
        $evidenceId = $evidenceResponse->json('data.id');

        $this->getJson('/api/orientation/plans?year=2026')
            ->assertOk()
            ->assertJsonPath('data.stats.actions', 1)
            ->assertJsonPath('data.stats.activities', 1)
            ->assertJsonPath('data.stats.evidences', 1)
            ->assertJsonPath('data.actions.0.related_plans.0.name', 'Plan de Afectividad, Sexualidad y Género');

        $events = $this->getJson("/api/orientation/plans/{$planId}/calendar?start=2026-05-01&end=2026-06-01")
            ->assertOk()
            ->json('data');
        $this->assertEqualsCanonicalizing(['action', 'activity'], collect($events)->pluck('extendedProps.type')->all());

        $this->getJson("/api/orientation/plans/{$planId}/statistics")
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.summary.actions', 1)
            ->assertJsonPath('data.summary.activities', 1)
            ->assertJsonPath('data.summary.completed_activities', 1)
            ->assertJsonPath('data.summary.evidences', 1)
            ->assertJsonPath('data.summary.related_plans', 1)
            ->assertJsonPath('data.summary.traceability', 100)
            ->assertJsonPath('data.monthly.3.actions', 1)
            ->assertJsonPath('data.monthly.4.activities', 1)
            ->assertJsonPath('data.action_performance.0.evidences_count', 1)
            ->assertJsonPath('data.action_performance.0.related_plans_count', 1);

        $this->get("/api/orientation/evidences/{$evidenceId}/download")->assertOk();
        $this->assertSame(1, OrientationAction::query()->count());
        $this->assertSame(1, OrientationActivity::query()->count());
        $this->assertSame(1, OrientationEvidence::query()->count());
    }

    public function test_activity_contributions_recalculate_action_progress_and_cannot_exceed_one_hundred_percent(): void
    {
        $user = $this->orientationUser();
        $this->actingAs($user, 'sanctum');
        $plan = OrientationPlan::query()->create([
            'year' => 2026,
            'title' => 'Plan 2026',
            'status' => 'active',
            'created_by' => $user->id,
        ]);
        $action = OrientationAction::query()->create([
            'orientation_plan_id' => $plan->id,
            'title' => 'Acción con avance automático',
            'status' => 'planned',
            'progress' => 0,
        ]);

        $firstActivityId = $this->postJson("/api/orientation/actions/{$action->id}/activities", [
            'title' => 'Primera actividad',
            'starts_at' => '2026-04-10 09:00:00',
            'status' => 'completed',
            'contribution_percent' => 30,
            'completion_percent' => 25,
        ])->assertCreated()
            ->assertJsonPath('data.progress', 30)
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.activities.0.completion_percent', 100)
            ->json('activity_id');

        $secondActivityId = $this->postJson("/api/orientation/actions/{$action->id}/activities", [
            'title' => 'Segunda actividad',
            'starts_at' => '2026-05-10 09:00:00',
            'status' => 'in_progress',
            'contribution_percent' => 50,
            'completion_percent' => 50,
        ])->assertCreated()
            ->assertJsonPath('data.progress', 55)
            ->assertJsonPath('data.activity_contribution.allocated', 80)
            ->assertJsonPath('data.activity_contribution.remaining', 20)
            ->json('activity_id');

        $this->putJson("/api/orientation/actions/{$action->id}", [
            'title' => 'Acción con avance automático actualizada',
            'status' => 'in_progress',
            'progress' => 99,
        ])->assertOk()
            ->assertJsonPath('data.progress', 55)
            ->assertJsonPath('data.status', 'in_progress');

        $this->postJson("/api/orientation/actions/{$action->id}/activities", [
            'title' => 'Actividad que excede el aporte disponible',
            'starts_at' => '2026-06-10 09:00:00',
            'status' => 'scheduled',
            'contribution_percent' => 21,
            'completion_percent' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('contribution_percent');

        $this->putJson("/api/orientation/activities/{$secondActivityId}", [
            'title' => 'Segunda actividad',
            'starts_at' => '2026-05-10 09:00:00',
            'status' => 'completed',
            'contribution_percent' => 50,
            'completion_percent' => 50,
        ])->assertOk()->assertJsonPath('data.progress', 80);

        $thirdActivityId = $this->postJson("/api/orientation/actions/{$action->id}/activities", [
            'title' => 'Actividad final',
            'starts_at' => '2026-06-10 09:00:00',
            'status' => 'scheduled',
            'contribution_percent' => 20,
            'completion_percent' => 0,
        ])->assertCreated()
            ->assertJsonPath('data.progress', 80)
            ->assertJsonPath('data.activity_contribution.allocated', 100)
            ->json('activity_id');

        $this->putJson("/api/orientation/activities/{$thirdActivityId}", [
            'title' => 'Actividad final',
            'starts_at' => '2026-06-10 09:00:00',
            'status' => 'completed',
            'contribution_percent' => 20,
            'completion_percent' => 0,
        ])->assertOk()
            ->assertJsonPath('data.progress', 100)
            ->assertJsonPath('data.status', 'completed');

        $this->assertSame(100, $action->refresh()->progress);
        $this->assertSame('completed', $action->status);
        $this->assertSame(3, OrientationActivity::query()->count());
        $this->assertNotNull($firstActivityId);
    }

    public function test_zero_contribution_activities_preserve_manual_action_progress(): void
    {
        $user = $this->orientationUser();
        $this->actingAs($user, 'sanctum');
        $plan = OrientationPlan::query()->create([
            'year' => 2026,
            'title' => 'Plan 2026',
            'status' => 'active',
            'created_by' => $user->id,
        ]);
        $action = OrientationAction::query()->create([
            'orientation_plan_id' => $plan->id,
            'title' => 'Acción con seguimiento manual',
            'status' => 'in_progress',
            'progress' => 45,
        ]);

        $this->postJson("/api/orientation/actions/{$action->id}/activities", [
            'title' => 'Actividad informativa sin aporte',
            'starts_at' => '2026-04-10 09:00:00',
            'status' => 'scheduled',
            'contribution_percent' => 0,
            'completion_percent' => 0,
        ])->assertCreated()
            ->assertJsonPath('data.progress', 45)
            ->assertJsonPath('data.status', 'in_progress');

        $this->putJson("/api/orientation/actions/{$action->id}", [
            'title' => 'Acción manual actualizada',
            'status' => 'in_progress',
            'progress' => 60,
        ])->assertOk()
            ->assertJsonPath('data.progress', 60)
            ->assertJsonPath('data.status', 'in_progress');

        $automaticActivityId = $this->postJson("/api/orientation/actions/{$action->id}/activities", [
            'title' => 'Actividad que activa el avance automático',
            'starts_at' => '2026-05-10 09:00:00',
            'status' => 'completed',
            'contribution_percent' => 20,
            'completion_percent' => 100,
        ])->assertCreated()
            ->assertJsonPath('data.progress', 20)
            ->json('activity_id');

        $this->putJson("/api/orientation/activities/{$automaticActivityId}", [
            'title' => 'Actividad que deja de aportar',
            'starts_at' => '2026-05-10 09:00:00',
            'status' => 'completed',
            'contribution_percent' => 0,
            'completion_percent' => 100,
        ])->assertOk()
            ->assertJsonPath('data.progress', 0)
            ->assertJsonPath('data.status', 'planned');
    }

    public function test_dates_and_evidence_activity_must_belong_to_the_same_annual_context(): void
    {
        $user = $this->orientationUser();
        $this->actingAs($user, 'sanctum');
        $plan = OrientationPlan::query()->create([
            'year' => 2026,
            'title' => 'Plan 2026',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $this->postJson("/api/orientation/plans/{$plan->id}/actions", [
            'title' => 'Acción fuera del periodo',
            'start_date' => '2027-03-01',
            'end_date' => '2027-03-02',
            'status' => 'planned',
            'progress' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors(['start_date', 'end_date']);

        $firstAction = OrientationAction::query()->create([
            'orientation_plan_id' => $plan->id,
            'title' => 'Acción uno',
            'status' => 'planned',
            'progress' => 0,
        ]);
        $secondAction = OrientationAction::query()->create([
            'orientation_plan_id' => $plan->id,
            'title' => 'Acción dos',
            'status' => 'planned',
            'progress' => 0,
        ]);
        $foreignActivity = OrientationActivity::query()->create([
            'orientation_action_id' => $secondAction->id,
            'title' => 'Actividad de otra acción',
            'starts_at' => '2026-05-05 10:00:00',
            'status' => 'scheduled',
        ]);

        $this->postJson("/api/orientation/actions/{$firstAction->id}/evidences", [
            'title' => 'Evidencia fuera del periodo',
            'evidence_type' => 'other',
            'occurred_on' => '2027-01-10',
            'external_url' => 'https://example.test/evidencia-2027',
        ])->assertUnprocessable()->assertJsonValidationErrors('occurred_on');

        $this->postJson("/api/orientation/actions/{$firstAction->id}/evidences", [
            'title' => 'Enlace de respaldo',
            'evidence_type' => 'other',
            'orientation_activity_id' => $foreignActivity->id,
            'external_url' => 'https://example.test/evidencia',
        ])->assertUnprocessable()->assertJsonValidationErrors('orientation_activity_id');
    }

    public function test_action_deletion_cascades_execution_records_and_removes_private_files(): void
    {
        Storage::fake('local');
        $user = $this->orientationUser();
        $this->actingAs($user, 'sanctum');
        $plan = OrientationPlan::query()->create([
            'year' => 2026,
            'title' => 'Plan 2026',
            'status' => 'active',
            'created_by' => $user->id,
        ]);
        $action = OrientationAction::query()->create([
            'orientation_plan_id' => $plan->id,
            'title' => 'Acción que será eliminada',
            'status' => 'planned',
            'progress' => 0,
        ]);
        $action->responsibleUsers()->attach($user->id);
        $relatedPlan = $plan->relatedPlans()->create([
            'name' => 'Plan transversal que debe conservarse',
            'category' => 'institutional',
            'status' => 'active',
        ]);
        $action->relatedPlans()->attach($relatedPlan->id);
        $activity = $action->activities()->create([
            'title' => 'Actividad asociada',
            'starts_at' => '2026-05-10 09:00:00',
            'status' => 'completed',
            'contribution_percent' => 25,
            'completion_percent' => 100,
        ]);
        $storedPath = Storage::disk('local')->put('orientation/2026/actions/delete-test/evidencia.txt', 'respaldo');
        $evidence = $action->evidences()->create([
            'orientation_activity_id' => $activity->id,
            'title' => 'Evidencia privada',
            'evidence_type' => 'other',
            'storage_disk' => 'local',
            'file_path' => $storedPath,
            'original_name' => 'evidencia.txt',
            'uploaded_by' => $user->id,
        ]);

        $this->deleteJson("/api/orientation/actions/{$action->id}")
            ->assertOk()
            ->assertJsonPath('message', 'La acción «Acción que será eliminada» y sus registros asociados fueron eliminados.');

        $this->assertDatabaseMissing('orientation_actions', ['id' => $action->id]);
        $this->assertDatabaseMissing('orientation_activities', ['id' => $activity->id]);
        $this->assertDatabaseMissing('orientation_evidences', ['id' => $evidence->id]);
        $this->assertDatabaseMissing('orientation_action_responsibles', ['orientation_action_id' => $action->id]);
        $this->assertDatabaseMissing('orientation_action_related_plan', ['orientation_action_id' => $action->id]);
        $this->assertDatabaseHas('orientation_related_plans', ['id' => $relatedPlan->id]);
        Storage::disk('local')->assertMissing($storedPath);
    }

    public function test_view_permission_does_not_authorize_plan_mutation(): void
    {
        $role = Role::query()->create([
            'slug' => 'lector_orientacion',
            'name' => 'Lector de Orientación',
            'active' => true,
        ]);
        $role->permissions()->attach(Permission::query()->where('slug', 'orientation.view')->firstOrFail());
        $user = User::factory()->create(['active' => true]);
        $user->roles()->attach($role);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/orientation/plans?year=2026')
            ->assertOk()
            ->assertJsonPath('capabilities.can_manage_plan', false);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/orientation/plans', [
                'year' => 2026,
                'title' => 'Plan no autorizado',
                'status' => 'draft',
            ])
            ->assertForbidden();

        $plan = OrientationPlan::query()->create([
            'year' => 2026,
            'title' => 'Plan protegido',
            'status' => 'active',
        ]);
        $action = OrientationAction::query()->create([
            'orientation_plan_id' => $plan->id,
            'title' => 'Acción protegida',
            'status' => 'planned',
            'progress' => 0,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/orientation/actions/{$action->id}")
            ->assertForbidden();
        $this->assertDatabaseHas('orientation_actions', ['id' => $action->id]);
    }

    private function orientationUser(): User
    {
        $user = User::factory()->create([
            'active' => true,
            'user_type' => 'staff',
        ]);
        $user->roles()->attach(Role::query()->where('slug', 'orientacion')->firstOrFail());

        return $user;
    }
}
