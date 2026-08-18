<?php

namespace Tests\Feature\Psychology;

use App\Models\Permission;
use App\Models\Psychology\PsychologyCase;
use App\Models\Psychology\PsychologyReferral;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PsychologyModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_inspector_can_create_and_submit_a_referral(): void
    {
        $inspector = $this->userWithRole('inspectoria');
        $student = StudentProfile::factory()->create(['general_status' => 'activo']);
        Sanctum::actingAs($inspector);

        $response = $this->postJson('/api/psychology/referrals', $this->referralPayload($student, true));

        $response->assertCreated()->assertJsonPath('data.status', 'submitted');
        $this->assertMatchesRegularExpression('/^PSI-D-\d{4}-\d{6}$/', $response->json('data.code'));
        $this->assertDatabaseHas('psychology_referral_status_history', ['to_status' => 'submitted', 'changed_by' => $inspector->id]);
        $this->assertDatabaseHas('psychology_audit_events', ['action' => 'referral.created', 'user_id' => $inspector->id]);
    }

    public function test_inspector_cannot_view_another_inspectors_referral(): void
    {
        $owner = $this->userWithRole('inspectoria');
        $other = $this->userWithRole('inspectoria');
        $referral = PsychologyReferral::factory()->create(['referred_by_user_id' => $owner->id]);
        Sanctum::actingAs($other);
        $this->getJson("/api/psychology/referrals/{$referral->id}")->assertForbidden();
    }

    public function test_submitted_referral_cannot_be_freely_edited_or_skip_transitions(): void
    {
        $inspector = $this->userWithRole('inspectoria');
        Sanctum::actingAs($inspector);
        $student = StudentProfile::factory()->create(['general_status' => 'activo']);
        $created = $this->postJson('/api/psychology/referrals', $this->referralPayload($student, true))->assertCreated();
        $id = $created->json('data.id');
        $this->putJson("/api/psychology/referrals/{$id}", $this->referralPayload($student, false))->assertForbidden();
        $this->postJson("/api/psychology/referrals/{$id}/transition", ['status' => 'completed', 'reason' => 'Intento inválido'])->assertForbidden();
    }

    public function test_coordinator_can_assign_and_psychologist_can_open_case(): void
    {
        $inspector = $this->userWithRole('inspectoria');
        $coordinator = $this->userWithRole('coordinador_psicologia');
        $psychologist = $this->userWithRole('psicologo');
        $referral = PsychologyReferral::factory()->create(['referred_by_user_id' => $inspector->id, 'status' => 'submitted', 'referred_at' => now(), 'code' => 'PSI-D-'.now()->format('Y').'-000001']);
        Sanctum::actingAs($coordinator);
        $this->postJson("/api/psychology/referrals/{$referral->id}/assign", ['user_id' => $psychologist->id, 'reason' => 'Distribución de carga'])->assertOk()->assertJsonPath('data.assigned_user.id', $psychologist->id);
        $this->postJson("/api/psychology/referrals/{$referral->id}/transition", ['status' => 'completed', 'reason' => 'Intento de salto'])->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->postJson("/api/psychology/referrals/{$referral->id}/transition", ['status' => 'under_review', 'reason' => 'Antecedentes revisados'])->assertOk();
        $this->postJson("/api/psychology/referrals/{$referral->id}/transition", ['status' => 'accepted', 'reason' => 'Corresponde al área'])->assertOk();

        Sanctum::actingAs($psychologist);
        $response = $this->postJson("/api/psychology/referrals/{$referral->id}/open-case", ['priority' => 'high', 'confidentiality' => 'psychology_team', 'general_reason' => 'Acompañamiento psicoeducativo', 'objectives' => 'Evaluar necesidades y acordar apoyos.']);
        $response->assertCreated()->assertJsonPath('data.responsible_user.id', $psychologist->id);
        $this->assertDatabaseHas('psychology_case_assignments', ['case_id' => $response->json('data.id'), 'user_id' => $psychologist->id, 'role' => 'primary']);
    }

    public function test_private_activity_note_is_hidden_without_explicit_permission(): void
    {
        $psychologist = $this->userWithRole('psicologo');
        $viewer = User::factory()->create(['active' => true]);
        $case = PsychologyCase::factory()->create(['responsible_user_id' => $psychologist->id]);
        $case->collaborators()->attach($viewer->id, ['visibility' => 'interdisciplinary_team']);
        $role = Role::query()->create(['slug' => 'psychology_limited_'.uniqid(), 'name' => 'Apoyo limitado', 'active' => true]);
        $permissionIds = Permission::query()->whereIn('slug', ['psychology.access', 'psychology.cases.view_assigned'])->pluck('id');
        $role->permissions()->sync($permissionIds);
        $viewer->roles()->sync([$role->id]);
        $case->activities()->create(['responsible_user_id' => $psychologist->id, 'type' => 'student_interview', 'activity_on' => now(), 'institutional_summary' => 'Resumen compartible.', 'private_note' => 'Contenido profesional reservado.', 'general_background' => 'Antecedentes clínicos reservados.', 'interviewee_rut' => '12.345.678-5', 'acknowledged_rut' => '9.876.543-2', 'visibility' => 'psychology_team', 'status' => 'finalized', 'created_by' => $psychologist->id]);
        Sanctum::actingAs($viewer);
        $this->getJson("/api/psychology/cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.activities.0.private_note', null)
            ->assertJsonPath('data.activities.0.general_background', null)
            ->assertJsonPath('data.activities.0.interviewee_rut', null)
            ->assertJsonPath('data.activities.0.acknowledged_rut', null)
            ->assertJsonPath('data.activities.0.institutional_summary', 'Resumen compartible.');
    }

    public function test_interview_form_saves_structured_confidential_data_and_assigns_sequence(): void
    {
        $psychologist = $this->userWithRole('psicologo');
        $case = PsychologyCase::factory()->create(['responsible_user_id' => $psychologist->id]);
        Sanctum::actingAs($psychologist);

        $payload = [
            'type' => 'student_interview',
            'activity_on' => now()->toDateString(),
            'starts_at' => '10:00',
            'ends_at' => '10:45',
            'modality' => 'presencial',
            'participant_types' => ['student', 'guardian'],
            'interviewee_type' => 'student',
            'interviewee_name' => 'Estudiante de prueba',
            'interviewee_rut' => '12.345.678-5',
            'general_background' => 'Antecedente sensible para el equipo profesional.',
            'institutional_summary' => 'Se realizó entrevista y se acordó seguimiento.',
            'agreements' => 'Revisar avances la próxima semana.',
            'next_interview_at' => now()->addWeek()->setTime(11, 0)->format('Y-m-d H:i:s'),
            'acknowledgement_status' => 'acknowledged',
            'acknowledged_name' => 'Estudiante de prueba',
            'acknowledged_rut' => '12.345.678-5',
            'visibility' => 'psychology_team',
            'status' => 'finalized',
        ];

        $first = $this->postJson("/api/psychology/cases/{$case->id}/activities", $payload)
            ->assertCreated()
            ->assertJsonPath('data.interview_number', 1)
            ->assertJsonPath('data.interviewer_name_snapshot', $psychologist->name)
            ->assertJsonPath('data.acknowledgement_status', 'acknowledged');

        $this->postJson("/api/psychology/cases/{$case->id}/activities", $payload)
            ->assertCreated()
            ->assertJsonPath('data.interview_number', 2);

        $rawActivity = DB::table('psychology_activities')->where('id', $first->json('data.id'))->first();
        $this->assertNotSame($payload['general_background'], $rawActivity->general_background);
        $this->assertNotSame($payload['interviewee_rut'], $rawActivity->interviewee_rut);
        $this->assertNotNull($rawActivity->acknowledged_at);
        $this->assertDatabaseHas('psychology_cases', [
            'id' => $case->id,
            'next_review_on' => now()->addWeek()->toDateString(),
        ]);
    }

    public function test_critical_risk_requires_immediate_action_responsible_and_time(): void
    {
        $psychologist = $this->userWithRole('psicologo');
        $case = PsychologyCase::factory()->create(['responsible_user_id' => $psychologist->id]);
        Sanctum::actingAs($psychologist);
        $this->postJson("/api/psychology/cases/{$case->id}/risk-assessments", ['risk_type' => 'emotional_crisis', 'level' => 'critical'])->assertUnprocessable()->assertJsonValidationErrors(['immediate_action', 'response_responsible_user_id', 'response_at', 'protocol_reference']);
        $this->postJson("/api/psychology/cases/{$case->id}/risk-assessments", ['risk_type' => 'emotional_crisis', 'level' => 'critical', 'immediate_action' => 'Activar medidas institucionales de resguardo.', 'response_responsible_user_id' => $psychologist->id, 'response_at' => now()->toDateTimeString(), 'protocol_reference' => 'Protocolo institucional vigente'])->assertCreated();
        $this->assertDatabaseHas('psychology_cases', ['id' => $case->id, 'priority' => 'critical']);
        $this->assertDatabaseHas('psychology_protective_actions', ['responsible_user_id' => $psychologist->id]);
    }

    public function test_private_document_download_is_authorized_and_audited(): void
    {
        Storage::fake('local');
        $psychologist = $this->userWithRole('psicologo');
        $case = PsychologyCase::factory()->create(['responsible_user_id' => $psychologist->id]);
        Sanctum::actingAs($psychologist);
        $upload = $this->post('/api/psychology/documents', ['case_id' => $case->id, 'category' => 'background', 'visibility' => 'private_psychology', 'document' => UploadedFile::fake()->create('antecedente.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated();
        $documentId = $upload->json('data.id');
        $this->get("/api/psychology/documents/{$documentId}/download")->assertOk();
        $this->assertDatabaseHas('psychology_audit_events', ['action' => 'document.downloaded', 'auditable_id' => $documentId]);
        $this->assertStringNotContainsString('private_path', json_encode($upload->json('data')));
    }

    public function test_management_role_receives_aggregate_report_but_not_nominal_data(): void
    {
        PsychologyReferral::factory()->count(2)->create(['status' => 'submitted', 'referred_at' => now()]);
        $director = $this->userWithRole('direccion');
        Sanctum::actingAs($director);
        $this->getJson('/api/psychology/reports')->assertOk()->assertJsonPath('counts.referrals', 2)->assertJsonPath('nominal', null);
        $this->getJson('/api/psychology/reports?nominal=1')->assertForbidden();
        $this->getJson('/api/psychology/referrals')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_case_close_and_reopen_preserve_history(): void
    {
        $coordinator = $this->userWithRole('coordinador_psicologia');
        $case = PsychologyCase::factory()->create(['responsible_user_id' => $coordinator->id]);
        Sanctum::actingAs($coordinator);
        $this->postJson("/api/psychology/cases/{$case->id}/close", ['closure_type' => 'objectives_met', 'reason' => 'Objetivos cumplidos', 'result_summary' => 'Se completaron las acciones planificadas.'])->assertOk()->assertJsonPath('data.status', 'closed');
        $this->postJson("/api/psychology/cases/{$case->id}/reopen", ['reason' => 'Nueva derivación relacionada'])->assertOk()->assertJsonPath('data.status', 'reopened');
        $this->assertDatabaseHas('psychology_case_closures', ['case_id' => $case->id]);
        $this->assertDatabaseHas('psychology_case_reopenings', ['case_id' => $case->id]);
    }

    public function test_super_admin_can_see_and_manage_the_complete_psychology_domain(): void
    {
        $superRole = Role::query()->firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin', 'active' => true]);
        $superAdmin = User::factory()->create(['active' => true]);
        $superAdmin->roles()->sync([$superRole->id]);
        $case = PsychologyCase::factory()->create();
        PsychologyReferral::factory()->create(['status' => 'submitted']);
        Sanctum::actingAs($superAdmin);
        $this->getJson("/api/psychology/cases/{$case->id}")->assertOk();
        $this->getJson('/api/psychology/referrals')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/psychology/reports')->assertOk()->assertJsonPath('counts.referrals', 1);
        $this->getJson('/api/psychology/reports?nominal=1')->assertOk()->assertJsonCount(1, 'nominal');
        $this->getJson('/api/psychology/catalogs')->assertOk()->assertJsonPath('capabilities.view_cases', true)->assertJsonPath('capabilities.config', true)->assertJsonPath('capabilities.audit', true);
        $this->getJson('/api/psychology/students')->assertOk();
    }

    public function test_permissions_and_views_are_registered_per_role(): void
    {
        $this->assertSame(23, Permission::query()->where('slug', 'like', 'psychology.%')->count());
        $this->assertDatabaseHas('system_modules', ['slug' => 'psychology', 'frontend_route' => null, 'icon' => 'bx-bulb', 'active' => true]);
        $this->assertSame(9, DB::table('system_modules')->where('parent_id', DB::table('system_modules')->where('slug', 'psychology')->value('id'))->count());

        $inspectoriaViews = $this->psychologyViewsForRole('inspectoria');
        $this->assertContains('psychology_dashboard', $inspectoriaViews);
        $this->assertContains('psychology_referrals', $inspectoriaViews);
        $this->assertNotContains('psychology_cases', $inspectoriaViews);

        $directionViews = $this->psychologyViewsForRole('direccion');
        $this->assertContains('psychology_dashboard', $directionViews);
        $this->assertContains('psychology_reports', $directionViews);
        $this->assertNotContains('psychology_referrals', $directionViews);

        $this->assertCount(10, $this->psychologyViewsForRole('coordinador_psicologia'));

        $psychologist = Role::query()->where('slug', 'psicologo')->firstOrFail();
        $this->assertSame('Psicóloga', $psychologist->name);
        $this->assertSame(23, $psychologist->permissions()->where('slug', 'like', 'psychology.%')->count());
        $this->assertCount(10, $this->psychologyViewsForRole('psicologo'));

        $superAdmin = Role::query()->where('slug', 'super_admin')->firstOrFail();
        $this->assertSame(23, $superAdmin->permissions()->where('slug', 'like', 'psychology.%')->count());
        $this->assertCount(10, $this->psychologyViewsForRole('super_admin'));
    }

    public function test_large_nominal_export_is_queued_stored_privately_and_owner_scoped(): void
    {
        Storage::fake('local');
        $coordinator = $this->userWithRole('coordinador_psicologia');
        PsychologyReferral::factory()->create(['status' => 'submitted', 'referred_at' => now()]);
        Sanctum::actingAs($coordinator);
        $response = $this->postJson('/api/psychology/exports', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()])->assertAccepted();
        $exportId = $response->json('data.id');
        $this->assertDatabaseHas('psychology_exports', ['id' => $exportId, 'status' => 'completed', 'created_by' => $coordinator->id]);
        $this->getJson("/api/psychology/exports/{$exportId}")->assertOk()->assertJsonPath('data.status', 'completed');
        $this->get("/api/psychology/exports/{$exportId}/download")->assertOk();
        $other = $this->userWithRole('coordinador_psicologia');
        Sanctum::actingAs($other);
        $this->getJson("/api/psychology/exports/{$exportId}")->assertNotFound();
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->where('slug', $slug)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function psychologyViewsForRole(string $roleSlug): array
    {
        return DB::table('role_system_module')
            ->join('roles', 'roles.id', '=', 'role_system_module.role_id')
            ->join('system_modules', 'system_modules.id', '=', 'role_system_module.system_module_id')
            ->where('roles.slug', $roleSlug)
            ->where(fn ($query) => $query->where('system_modules.slug', 'psychology')->orWhere('system_modules.slug', 'like', 'psychology_%'))
            ->pluck('system_modules.slug')
            ->all();
    }

    private function referralPayload(StudentProfile $student, bool $submit): array
    {
        return ['student_profile_id' => $student->id, 'suggested_urgency' => 'medium', 'primary_reason' => 'Bienestar emocional', 'secondary_reasons' => null, 'observed_facts' => 'Durante la jornada se observó aislamiento sostenido y solicitud de apoyo.', 'approximate_started_on' => now()->subWeek()->toDateString(), 'people_involved' => 'Estudiante y profesora jefe', 'measures_taken' => 'Contención y conversación breve.', 'known_previous_interventions' => null, 'observed_risk_indicators' => null, 'immediate_response_needed' => false, 'guardian_informed' => false, 'guardian_contact_status' => 'pending', 'observations' => null, 'purpose_declaration_accepted' => true, 'submit' => $submit];
    }
}
