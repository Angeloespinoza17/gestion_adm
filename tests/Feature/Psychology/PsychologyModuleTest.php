<?php

namespace Tests\Feature\Psychology;

use App\Models\Permission;
use App\Models\Psychology\PsychologyCase;
use App\Models\Psychology\PsychologyReferral;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\SystemModule;
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
        $unqualified = User::factory()->create(['active' => true]);
        $this->postJson("/api/psychology/referrals/{$referral->id}/assign", ['user_id' => $unqualified->id, 'reason' => 'Asignación inválida'])->assertUnprocessable()->assertJsonValidationErrors(['user_id']);
        $this->postJson("/api/psychology/referrals/{$referral->id}/assign", ['user_id' => $psychologist->id, 'reason' => 'Distribución de carga'])->assertOk()->assertJsonPath('data.assigned_user.id', $psychologist->id);
        $this->postJson("/api/psychology/referrals/{$referral->id}/transition", ['status' => 'completed', 'reason' => 'Intento de salto'])->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->postJson("/api/psychology/referrals/{$referral->id}/transition", ['status' => 'under_review', 'reason' => 'Antecedentes revisados'])->assertOk();
        $this->postJson("/api/psychology/referrals/{$referral->id}/transition", ['status' => 'accepted', 'reason' => 'Corresponde al área'])->assertOk();

        Sanctum::actingAs($psychologist);
        $response = $this->postJson("/api/psychology/referrals/{$referral->id}/open-case", ['responsible_user_id' => null, 'priority' => 'high', 'confidentiality' => 'psychology_team', 'general_reason' => 'Acompañamiento psicoeducativo', 'objectives' => 'Evaluar necesidades y acordar apoyos.']);
        $response->assertCreated()->assertJsonPath('data.responsible_user.id', $psychologist->id);
        $response->assertJsonPath('data.origin', 'referral');
        $this->assertDatabaseHas('psychology_case_assignments', ['case_id' => $response->json('data.id'), 'user_id' => $psychologist->id, 'role' => 'primary']);
    }

    public function test_psychologist_can_open_direct_case_without_referral(): void
    {
        $psychologist = $this->userWithRole('psicologo');
        $otherPsychologist = $this->userWithRole('psicologo');
        $student = StudentProfile::factory()->create([
            'general_status' => 'activo',
            'rut' => '27.359.284-9',
            'guardian_name' => 'Francisca León',
            'guardian_rut' => '15.123.456-7',
            'guardian_relationship' => 'Madre',
            'guardian_phone' => '+56 9 1234 5678',
            'guardian_email' => 'francisca@example.test',
        ]);
        $nextReviewOn = now()->addDay()->toDateString();
        Sanctum::actingAs($psychologist);

        $response = $this->postJson('/api/psychology/cases', [
            'student_profile_id' => $student->id,
            'responsible_user_id' => null,
            'priority' => 'medium',
            'confidentiality' => 'private_psychology',
            'general_reason' => 'Atención profesional iniciada directamente.',
            'objectives' => 'Evaluar necesidades y definir apoyos.',
            'next_review_on' => $nextReviewOn,
        ])->assertCreated()
            ->assertJsonPath('data.origin', 'direct')
            ->assertJsonPath('data.next_review_on', $nextReviewOn)
            ->assertJsonPath('data.student.id', $student->id)
            ->assertJsonPath('data.student.rut', '27.359.284-9')
            ->assertJsonPath('data.student.guardian_name', 'Francisca León')
            ->assertJsonPath('data.student.guardian_rut', '15.123.456-7')
            ->assertJsonPath('data.student.guardian_relationship', 'Madre')
            ->assertJsonPath('data.student.guardian_phone', '+56 9 1234 5678')
            ->assertJsonPath('data.student.guardian_email', 'francisca@example.test')
            ->assertJsonPath('data.responsible_user.id', $psychologist->id);

        $caseId = $response->json('data.id');
        $this->assertDatabaseHas('psychology_cases', [
            'id' => $caseId,
            'student_profile_id' => $student->id,
            'origin_referral_id' => null,
            'responsible_user_id' => $psychologist->id,
        ]);
        $this->assertDatabaseHas('psychology_case_assignments', [
            'case_id' => $caseId,
            'user_id' => $psychologist->id,
            'reason' => 'Apertura directa sin derivación',
        ]);
        $this->assertDatabaseHas('psychology_audit_events', [
            'action' => 'case.opened',
            'auditable_id' => $caseId,
            'reason' => 'Apertura directa sin derivación',
        ]);

        $this->postJson('/api/psychology/cases', [
            'student_profile_id' => $student->id,
            'priority' => 'high',
            'confidentiality' => 'private_psychology',
            'general_reason' => 'Segundo caso activo no permitido.',
        ])->assertUnprocessable()->assertJsonValidationErrors(['student_profile_id']);

        Sanctum::actingAs($otherPsychologist);
        $this->getJson("/api/psychology/cases/{$caseId}")->assertForbidden();
    }

    public function test_psychologist_can_edit_functional_case_fields_with_an_audited_reason(): void
    {
        $psychologist = $this->userWithRole('psicologo');
        $otherPsychologist = $this->userWithRole('psicologo');
        $case = PsychologyCase::factory()->create([
            'responsible_user_id' => $psychologist->id,
            'general_reason' => 'Motivo inicial.',
            'priority' => 'medium',
            'confidentiality' => 'private_psychology',
            'guardian_information_status' => 'pending',
        ]);
        $historicalReviewDate = now()->subMonth()->toDateString();

        Sanctum::actingAs($psychologist);
        $this->patchJson("/api/psychology/cases/{$case->id}", [
            'general_reason' => 'Motivo actualizado con antecedentes revisados.',
            'status' => 'assessment',
            'objectives' => 'Ajustar el acompañamiento y monitorear acuerdos.',
            'categories' => 'bienestar, seguimiento',
            'next_action' => 'Contactar al apoderado.',
            'next_review_on' => $historicalReviewDate,
            'priority' => 'high',
            'confidentiality' => 'psychology_team',
            'guardian_information_status' => 'informed',
            'change_reason' => 'Corrección y actualización acordada del expediente.',
        ])->assertOk()
            ->assertJsonPath('data.general_reason', 'Motivo actualizado con antecedentes revisados.')
            ->assertJsonPath('data.next_review_on', $historicalReviewDate)
            ->assertJsonPath('data.status', 'assessment')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.guardian_information_status', 'informed');

        $this->assertDatabaseHas('psychology_cases', [
            'id' => $case->id,
            'updated_by' => $psychologist->id,
            'status' => 'assessment',
            'priority' => 'high',
            'next_review_on' => $historicalReviewDate,
        ]);
        $audit = DB::table('psychology_audit_events')
            ->where('action', 'case.updated')
            ->where('auditable_id', $case->id)
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame('Corrección y actualización acordada del expediente.', $audit->reason);
        $this->assertSame('[PROTEGIDO]', json_decode($audit->old_values, true)['general_reason']);
        $this->assertSame('[PROTEGIDO]', json_decode($audit->new_values, true)['objectives']);

        Sanctum::actingAs($otherPsychologist);
        $this->patchJson("/api/psychology/cases/{$case->id}", [
            'general_reason' => 'Intento fuera del alcance.',
            'status' => 'paused',
            'priority' => 'low',
            'confidentiality' => 'psychology_team',
            'guardian_information_status' => 'pending',
            'change_reason' => 'Intento de edición no autorizado.',
        ])->assertForbidden();
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
        $activity = $case->activities()->create(['responsible_user_id' => $psychologist->id, 'type' => 'student_interview', 'activity_on' => now(), 'institutional_summary' => 'Resumen compartible.', 'private_note' => 'Contenido profesional reservado.', 'general_background' => 'Antecedentes clínicos reservados.', 'interviewee_rut' => '12.345.678-5', 'acknowledged_rut' => '9.876.543-2', 'visibility' => 'psychology_team', 'status' => 'finalized', 'created_by' => $psychologist->id]);
        Sanctum::actingAs($viewer);
        $this->getJson("/api/psychology/cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.activities.0.private_note', null)
            ->assertJsonPath('data.activities.0.general_background', null)
            ->assertJsonPath('data.activities.0.interviewee_rut', null)
            ->assertJsonPath('data.activities.0.acknowledged_rut', null)
            ->assertJsonPath('data.activities.0.institutional_summary', 'Resumen compartible.');
        $this->postJson("/api/psychology/activities/{$activity->id}/export")
            ->assertOk()
            ->assertJsonPath('data.activity.institutional_summary', 'Resumen compartible.')
            ->assertJsonPath('data.activity.general_background', null)
            ->assertJsonPath('data.activity.private_note', null)
            ->assertJsonPath('data.activity.interviewee_rut', null);
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
            'ends_at' => '10:00',
            'modality' => 'presencial',
            'participant_types' => ['student', 'guardian'],
            'interviewee_type' => 'student',
            'interviewee_name' => 'Estudiante de prueba',
            'interviewee_rut' => '12.345.678-5',
            'general_background' => 'Antecedente sensible para el equipo profesional.',
            'institutional_summary' => 'Se realizó entrevista y se acordó seguimiento.',
            'agreements' => 'Revisar avances la próxima semana.',
            'follow_up_type' => 'new_interview',
            'next_action_on' => now()->addWeek()->toDateString(),
            'acknowledgement_status' => 'acknowledged',
            'acknowledged_name' => 'Estudiante de prueba',
            'acknowledged_rut' => '12.345.678-5',
            'visibility' => 'psychology_team',
            'status' => 'finalized',
        ];

        $this->postJson("/api/psychology/cases/{$case->id}/activities", array_merge($payload, [
            'next_steps' => 'Acción sin fecha no válida.',
            'next_action_on' => null,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['next_action_on']);

        $first = $this->postJson("/api/psychology/cases/{$case->id}/activities", $payload)
            ->assertCreated()
            ->assertJsonPath('data.interview_number', 1)
            ->assertJsonPath('data.interviewer_name_snapshot', $psychologist->name)
            ->assertJsonPath('data.follow_up_type', 'new_interview')
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
        $this->postJson("/api/psychology/activities/{$first->json('data.id')}/export")
            ->assertOk()
            ->assertJsonPath('data.case.id', $case->id)
            ->assertJsonPath('data.activity.interview_number', 1)
            ->assertJsonPath('data.activity.follow_up_type', 'new_interview')
            ->assertJsonPath('data.activity.follow_up_type_label', 'Nueva entrevista')
            ->assertJsonPath('data.activity.interviewee_rut', '12.345.678-5')
            ->assertJsonPath('data.activity.general_background', 'Antecedente sensible para el equipo profesional.');
        $this->assertDatabaseHas('psychology_audit_events', [
            'action' => 'activity.pdf_exported',
            'auditable_id' => $first->json('data.id'),
            'user_id' => $psychologist->id,
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

    public function test_intervention_plan_can_be_exported_only_from_an_authorized_case_and_is_audited(): void
    {
        $psychologist = $this->userWithRole('psicologo');
        $otherPsychologist = $this->userWithRole('psicologo');
        $student = StudentProfile::factory()->create(['rut' => '12.345.678-5']);
        $case = PsychologyCase::factory()->create([
            'student_profile_id' => $student->id,
            'responsible_user_id' => $psychologist->id,
            'code' => 'PSI-2026-000099',
        ]);
        Sanctum::actingAs($psychologist);

        $created = $this->postJson("/api/psychology/cases/{$case->id}/plans", [
            'status' => 'active',
            'review_on' => now()->addMonth()->toDateString(),
            'general_situation' => 'Situación general protegida.',
            'general_objective' => 'Fortalecer estrategias de autorregulación.',
            'specific_objectives' => 'Identificar señales y aplicar apoyos acordados.',
            'planned_actions' => 'Sesiones quincenales y coordinación con familia.',
            'responsibles' => 'Psicóloga responsable y profesora jefe.',
            'frequency' => 'Quincenal',
            'estimated_start_on' => now()->toDateString(),
            'estimated_end_on' => now()->addMonths(3)->toDateString(),
            'monitoring_indicators' => 'Cumplimiento de acuerdos y reporte de avances.',
            'participants' => 'Estudiante y apoderado.',
            'family_coordination' => 'Contacto mensual con apoderado.',
            'teacher_coordination' => 'Revisión de apoyos en aula.',
        ])->assertCreated();

        $planId = $created->json('data.id');
        $this->postJson("/api/psychology/plans/{$planId}/export")
            ->assertOk()
            ->assertJsonPath('data.case.code', 'PSI-2026-000099')
            ->assertJsonPath('data.plan.current_version', 1)
            ->assertJsonPath('data.plan.version.general_objective', 'Fortalecer estrategias de autorregulación.')
            ->assertJsonPath('data.plan.version.frequency', 'Quincenal');
        $this->assertDatabaseHas('psychology_audit_events', [
            'action' => 'plan.pdf_exported',
            'auditable_id' => $planId,
            'user_id' => $psychologist->id,
        ]);

        Sanctum::actingAs($otherPsychologist);
        $this->postJson("/api/psychology/plans/{$planId}/export")->assertForbidden();
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
        $this->getJson('/api/psychology/reports')->assertOk()
            ->assertJsonPath('scope.type', 'institutional')
            ->assertJsonPath('counts.referrals', 2)
            ->assertJsonPath('nominal', null);
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

    public function test_psychologist_only_sees_assigned_cases_referrals_and_own_activities(): void
    {
        $psychologist = $this->userWithRole('psicologo');
        $otherPsychologist = $this->userWithRole('psicologo');
        $ownCase = PsychologyCase::factory()->create(['responsible_user_id' => $psychologist->id]);
        $otherCase = PsychologyCase::factory()->create(['responsible_user_id' => $otherPsychologist->id]);
        $ownActivity = $ownCase->activities()->create([
            'responsible_user_id' => $psychologist->id,
            'type' => 'student_interview',
            'activity_on' => now()->addDay()->toDateString(),
            'starts_at' => '09:00',
            'institutional_summary' => 'Atención propia.',
            'private_note' => 'Nota profesional propia.',
            'next_steps' => 'Revisar estrategias acordadas.',
            'next_action_on' => now()->addDays(5)->toDateString(),
            'follow_up_type' => 'phone_call',
            'visibility' => 'private_psychology',
            'status' => 'finalized',
            'created_by' => $psychologist->id,
        ]);
        $otherActivity = $ownCase->activities()->create([
            'responsible_user_id' => $otherPsychologist->id,
            'type' => 'guardian_interview',
            'activity_on' => now()->addDay()->toDateString(),
            'starts_at' => '11:00',
            'institutional_summary' => 'Atención de otra profesional.',
            'private_note' => 'Nota de otra profesional.',
            'next_steps' => 'Seguimiento que no debe ser visible.',
            'next_action_on' => now()->addDays(6)->toDateString(),
            'follow_up_type' => 'guardian_contact',
            'visibility' => 'private_psychology',
            'status' => 'draft',
            'created_by' => $otherPsychologist->id,
        ]);
        $ownTask = $ownCase->tasks()->create([
            'responsible_user_id' => $psychologist->id,
            'title' => 'Preparar material de atención.',
            'type' => 'case_action',
            'priority' => 'medium',
            'status' => 'pending',
            'due_at' => now()->addDays(3),
            'created_by' => $psychologist->id,
        ]);
        $ownCase->tasks()->create([
            'responsible_user_id' => $otherPsychologist->id,
            'title' => 'Tarea privada de otra profesional.',
            'type' => 'case_action',
            'priority' => 'medium',
            'status' => 'pending',
            'due_at' => now()->addDays(4),
            'created_by' => $otherPsychologist->id,
        ]);
        PsychologyReferral::factory()->create(['assigned_user_id' => $psychologist->id, 'status' => 'under_review']);
        PsychologyReferral::factory()->create(['assigned_user_id' => $otherPsychologist->id, 'status' => 'under_review']);

        Sanctum::actingAs($psychologist);

        $this->getJson('/api/psychology/cases')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownCase->id);
        $this->getJson("/api/psychology/cases/{$otherCase->id}")->assertForbidden();
        $this->postJson("/api/psychology/activities/{$otherActivity->id}/finalize")->assertForbidden();
        $this->postJson("/api/psychology/activities/{$otherActivity->id}/export")->assertNotFound();
        $this->getJson("/api/psychology/cases/{$ownCase->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.activities')
            ->assertJsonPath('data.activities.0.id', $ownActivity->id)
            ->assertJsonPath('data.activities.0.private_note', 'Nota profesional propia.');
        $this->getJson('/api/psychology/referrals')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.assigned_user.id', $psychologist->id);
        $this->getJson('/api/psychology/follow-ups')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.activity_id', $ownActivity->id)
            ->assertJsonPath('data.0.case_id', $ownCase->id)
            ->assertJsonPath('data.0.title', 'Llamada telefónica')
            ->assertJsonPath('data.0.description', 'Revisar estrategias acordadas.')
            ->assertJsonPath('data.0.follow_up_type', 'phone_call')
            ->assertJsonPath('data.0.status', 'pending');
        $this->getJson('/api/psychology/dashboard')
            ->assertOk()
            ->assertJsonPath('metrics.active_cases', 1)
            ->assertJsonPath('metrics.scheduled_sessions', 1)
            ->assertJsonCount(1, 'recent.tasks')
            ->assertJsonPath('recent.tasks.0.id', $ownTask->id);
        $this->getJson('/api/psychology/calendar?from='.now()->toDateString().'&to='.now()->addDays(2)->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'activity-'.$ownActivity->id);
        $this->getJson('/api/psychology/calendar?from='.now()->toDateString().'&to='.now()->addDays(7)->toDateString())
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.1.id', 'followup-'.$ownActivity->id)
            ->assertJsonPath('data.1.type', 'follow_up')
            ->assertJsonPath('data.1.follow_up_type', 'phone_call')
            ->assertJsonPath('data.2.id', 'task-'.$ownTask->id);
        $this->getJson('/api/psychology/catalogs')
            ->assertOk()
            ->assertJsonPath('capabilities.edit_case', true)
            ->assertJsonPath('capabilities.personal_scope', true)
            ->assertJsonPath('capabilities.full_domain', false);
    }

    public function test_psychology_report_contains_only_the_scoped_psychologists_statistics(): void
    {
        $psychologist = $this->userWithRole('psicologo');
        $otherPsychologist = $this->userWithRole('psicologo');
        $ownCase = PsychologyCase::factory()->create(['responsible_user_id' => $psychologist->id]);
        PsychologyCase::factory()->create(['responsible_user_id' => $otherPsychologist->id]);
        $ownCase->activities()->create([
            'responsible_user_id' => $psychologist->id,
            'type' => 'student_interview',
            'activity_on' => now()->toDateString(),
            'institutional_summary' => 'Atención propia contabilizable.',
            'visibility' => 'private_psychology',
            'status' => 'finalized',
            'created_by' => $psychologist->id,
        ]);
        $ownCase->activities()->create([
            'responsible_user_id' => $otherPsychologist->id,
            'type' => 'guardian_interview',
            'activity_on' => now()->toDateString(),
            'institutional_summary' => 'Atención ajena no contabilizable.',
            'visibility' => 'private_psychology',
            'status' => 'finalized',
            'created_by' => $otherPsychologist->id,
        ]);
        $ownCase->tasks()->create([
            'responsible_user_id' => $psychologist->id,
            'title' => 'Seguimiento propio.',
            'type' => 'follow_up',
            'priority' => 'medium',
            'status' => 'pending',
            'due_at' => now()->addDay(),
            'created_by' => $psychologist->id,
        ]);
        $ownCase->tasks()->create([
            'responsible_user_id' => $otherPsychologist->id,
            'title' => 'Seguimiento de otra profesional.',
            'type' => 'follow_up',
            'priority' => 'medium',
            'status' => 'pending',
            'due_at' => now()->addDay(),
            'created_by' => $otherPsychologist->id,
        ]);
        PsychologyReferral::factory()->create([
            'assigned_user_id' => $psychologist->id,
            'referred_by_user_id' => $otherPsychologist->id,
            'status' => 'under_review',
        ]);
        PsychologyReferral::factory()->create([
            'assigned_user_id' => $otherPsychologist->id,
            'referred_by_user_id' => $psychologist->id,
            'status' => 'under_review',
        ]);
        PsychologyReferral::factory()->create([
            'assigned_user_id' => $otherPsychologist->id,
            'status' => 'under_review',
        ]);

        Sanctum::actingAs($psychologist);

        $response = $this->getJson('/api/psychology/reports?from='.now()->subDay()->toDateString().'&to='.now()->addDay()->toDateString().'&professional_id='.$otherPsychologist->id)
            ->assertOk()
            ->assertJsonPath('scope.type', 'personal')
            ->assertJsonPath('scope.professional_id', $psychologist->id)
            ->assertJsonPath('scope.professional_name', $psychologist->name)
            ->assertJsonPath('counts.referrals', 1)
            ->assertJsonPath('counts.cases', 1)
            ->assertJsonPath('counts.unique_students', 2)
            ->assertJsonPath('counts.activities', 1)
            ->assertJsonPath('tasks.pending_followups', 1)
            ->assertJsonCount(1, 'workload')
            ->assertJsonPath('workload.0.label', $psychologist->name)
            ->assertJsonCount(1, 'activities_by_type')
            ->assertJsonPath('activities_by_type.0.total', 1);

        $this->assertSame(1, collect($response->json('activities_by_type'))->sum('total'));
    }

    public function test_attention_can_request_a_coordination_and_the_recipient_can_answer_without_case_access(): void
    {
        $psychologist = $this->userWithRole('psicologo');
        $recipient = User::factory()->create([
            'active' => true,
            'user_type' => 'staff',
            'name' => 'Docente solicitado',
        ]);
        $otherStaff = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $case = PsychologyCase::factory()->create(['responsible_user_id' => $psychologist->id]);
        $requestedFor = now()->addDays(3)->toDateString();

        Sanctum::actingAs($psychologist);
        $created = $this->postJson("/api/psychology/cases/{$case->id}/activities", [
            'type' => 'student_interview',
            'activity_on' => now()->toDateString(),
            'starts_at' => '09:00',
            'ends_at' => '09:30',
            'modality' => 'presencial',
            'participant_types' => ['student'],
            'interviewee_type' => 'student',
            'interviewee_name' => 'Estudiante de prueba',
            'institutional_summary' => 'Atención registrada con requerimiento institucional.',
            'visibility' => 'psychology_team',
            'status' => 'finalized',
            'coordination' => [
                'recipient_user_id' => $recipient->id,
                'coordination_type' => 'information_request',
                'subject' => 'Antecedentes pedagógicos recientes',
                'request_message' => 'Solicito antecedentes generales para coordinar apoyos.',
                'requested_for' => $requestedFor,
            ],
        ])->assertCreated();

        $coordinationId = DB::table('psychology_coordination_requests')->value('id');
        $this->assertNotNull($coordinationId);
        $this->assertDatabaseHas('psychology_coordination_requests', [
            'id' => $coordinationId,
            'case_id' => $case->id,
            'activity_id' => $created->json('data.id'),
            'requester_user_id' => $psychologist->id,
            'recipient_user_id' => $recipient->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $recipient->id,
        ]);
        $notificationData = json_decode(DB::table('notifications')->where('notifiable_id', $recipient->id)->value('data'), true);
        $this->assertSame('/mis-coordinaciones', $notificationData['action_url']);
        $this->assertArrayNotHasKey('case_id', $notificationData);

        $this->postJson("/api/psychology/cases/{$case->id}/coordinations", [
            'recipient_user_id' => $otherStaff->id,
            'coordination_type' => 'meeting',
            'subject' => 'Reunión de coordinación preventiva',
            'request_message' => 'Solicito coordinar una reunión de trabajo institucional.',
            'requested_for' => now()->subYear()->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.activity_id', null)
            ->assertJsonPath('data.requested_for', now()->subYear()->toDateString());

        Sanctum::actingAs($recipient);
        $inbox = $this->getJson('/api/psychology-coordinations/mine?direction=incoming')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $coordinationId)
            ->assertJsonPath('data.0.subject', 'Antecedentes pedagógicos recientes')
            ->assertJsonPath('data.0.direction', 'incoming');
        $this->assertArrayNotHasKey('case_id', $inbox->json('data.0'));
        $this->assertArrayNotHasKey('activity_id', $inbox->json('data.0'));
        $this->getJson("/api/psychology/cases/{$case->id}")->assertForbidden();
        $this->patchJson("/api/psychology-coordinations/{$coordinationId}/respond", [
            'status' => 'accepted',
            'response_message' => 'Acepto la coordinación y prepararé los antecedentes solicitados.',
        ])->assertOk()->assertJsonPath('data.status', 'accepted');

        Sanctum::actingAs($otherStaff);
        $this->patchJson("/api/psychology-coordinations/{$coordinationId}/respond", [
            'status' => 'rejected',
            'response_message' => 'Respuesta no autorizada.',
        ])->assertForbidden();

        Sanctum::actingAs($psychologist);
        $caseResponse = $this->getJson("/api/psychology/cases/{$case->id}")->assertOk();
        $answeredCoordination = collect($caseResponse->json('data.coordination_requests'))->firstWhere('id', $coordinationId);
        $this->assertSame('accepted', $answeredCoordination['status']);
        $this->assertSame('Acepto la coordinación y prepararé los antecedentes solicitados.', $answeredCoordination['response_message']);
        $this->assertSame($created->json('data.id'), $answeredCoordination['activity_id']);
        $calendar = $this->getJson('/api/psychology/calendar?from='.now()->toDateString().'&to='.now()->addDays(5)->toDateString())
            ->assertOk();
        $this->assertTrue(collect($calendar->json('data'))->contains(fn (array $event) => $event['id'] === 'coordination-'.$coordinationId && $event['type'] === 'coordination'));
        $this->assertDatabaseHas('psychology_audit_events', ['action' => 'coordination.requested', 'auditable_id' => $coordinationId]);
        $this->assertDatabaseHas('psychology_audit_events', ['action' => 'coordination.responded', 'auditable_id' => $coordinationId]);
    }

    public function test_super_admin_can_see_and_manage_the_complete_psychology_domain(): void
    {
        $superRole = Role::query()->firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin', 'active' => true]);
        $superAdmin = User::factory()->create(['active' => true]);
        $superAdmin->roles()->sync([$superRole->id]);
        $psychologist = $this->userWithRole('psicologo');
        $case = PsychologyCase::factory()->create(['responsible_user_id' => $psychologist->id]);
        $case->activities()->create([
            'responsible_user_id' => $psychologist->id,
            'type' => 'student_interview',
            'activity_on' => now(),
            'institutional_summary' => 'Resumen institucional.',
            'private_note' => 'Nota privada visible para superadmin.',
            'general_background' => 'Antecedente reservado.',
            'visibility' => 'private_psychology',
            'status' => 'finalized',
            'created_by' => $psychologist->id,
        ]);
        $case->riskAssessments()->create([
            'risk_type' => 'emotional_crisis',
            'level' => 'critical',
            'professional_rationale' => 'Evaluación reservada.',
            'created_by' => $case->responsible_user_id,
        ]);
        PsychologyReferral::factory()->create(['status' => 'submitted', 'assigned_user_id' => $psychologist->id]);
        Sanctum::actingAs($superAdmin);
        $this->getJson("/api/psychology/cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('data.activities.0.private_note', 'Nota privada visible para superadmin.')
            ->assertJsonPath('data.activities.0.general_background', 'Antecedente reservado.')
            ->assertJsonPath('data.risk_assessments.0.level', 'critical');
        $this->getJson('/api/psychology/referrals')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/psychology/dashboard')->assertOk()->assertJsonCount(1, 'recent.referrals');
        $this->getJson('/api/psychology/reports')->assertOk()
            ->assertJsonPath('scope.type', 'institutional')
            ->assertJsonPath('counts.referrals', 1);
        $this->getJson('/api/psychology/reports?from='.now()->subDay()->toDateString().'&to='.now()->addDay()->toDateString().'&professional_id='.$psychologist->id)->assertOk()
            ->assertJsonPath('scope.type', 'professional')
            ->assertJsonPath('scope.professional_id', $psychologist->id)
            ->assertJsonPath('scope.professional_name', $psychologist->name)
            ->assertJsonPath('counts.referrals', 1)
            ->assertJsonPath('counts.cases', 1)
            ->assertJsonPath('counts.unique_students', 2)
            ->assertJsonPath('counts.activities', 1);
        $this->getJson('/api/psychology/reports?nominal=1')->assertOk()->assertJsonCount(1, 'nominal');
        $this->getJson('/api/psychology/catalogs')->assertOk()
            ->assertJsonPath('capabilities.view_cases', true)
            ->assertJsonPath('capabilities.edit_case', true)
            ->assertJsonPath('capabilities.view_private', true)
            ->assertJsonPath('capabilities.reopen_case', true)
            ->assertJsonPath('capabilities.config', true)
            ->assertJsonPath('capabilities.audit', true)
            ->assertJsonPath('capabilities.full_domain', true);
        $this->getJson('/api/psychology/students')->assertOk();
        $this->getJson('/api/psychology/audit')->assertOk()->assertJsonCount(2, 'data');
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
        $this->assertSame(13, $psychologist->permissions()->where('slug', 'like', 'psychology.%')->count());
        $this->assertFalse($psychologist->permissions()->where('slug', 'psychology.referrals.view_all')->exists());
        $this->assertFalse($psychologist->permissions()->where('slug', 'psychology.cases.view_all')->exists());
        $this->assertFalse($psychologist->permissions()->where('slug', 'psychology.sensitive.override')->exists());
        $this->assertCount(8, $this->psychologyViewsForRole('psicologo'));

        $superAdmin = Role::query()->where('slug', 'super_admin')->firstOrFail();
        $this->assertSame(23, $superAdmin->permissions()->where('slug', 'like', 'psychology.%')->count());
        $this->assertCount(10, $this->psychologyViewsForRole('super_admin'));
    }

    public function test_legacy_broad_psychologist_assignments_are_effectively_scoped_without_deleting_rows(): void
    {
        $psychologist = $this->userWithRole('psicologo');
        $role = Role::query()->where('slug', 'psicologo')->firstOrFail();
        $legacyPermissions = Permission::query()->whereIn('slug', [
            'psychology.sensitive.override',
            'psychology.config.manage',
            'psychology.audit.view',
        ])->pluck('id');
        $legacyModules = SystemModule::query()->whereIn('slug', [
            'psychology_configuration',
            'psychology_audit',
        ])->pluck('id');

        $role->permissions()->syncWithoutDetaching($legacyPermissions);
        $role->modules()->syncWithoutDetaching($legacyModules);
        $psychologist->unsetRelation('roles');

        $this->assertTrue($role->permissions()->where('slug', 'psychology.sensitive.override')->exists());
        $this->assertFalse($psychologist->hasPermission('psychology.sensitive.override'));
        $this->assertFalse($psychologist->hasPermission('psychology.config.manage'));
        $this->assertFalse($psychologist->hasPermission('psychology.audit.view'));

        Sanctum::actingAs($psychologist);
        $effectivePermissions = collect($this->getJson('/api/me/permissions')->assertOk()->json('data'));
        $this->assertNotContains('psychology.sensitive.override', $effectivePermissions);
        $this->assertNotContains('psychology.config.manage', $effectivePermissions);
        $this->assertNotContains('psychology.audit.view', $effectivePermissions);

        $moduleSlugs = collect($this->getJson('/api/me/modules')->assertOk()->json('data'))->pluck('slug');
        $this->assertNotContains('psychology_configuration', $moduleSlugs);
        $this->assertNotContains('psychology_audit', $moduleSlugs);
    }

    public function test_student_lookup_returns_psychology_flags_without_per_student_queries(): void
    {
        $superRole = Role::query()->firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin', 'active' => true]);
        $superAdmin = User::factory()->create(['active' => true]);
        $superAdmin->roles()->sync([$superRole->id]);
        $psychologist = $this->userWithRole('psicologo');
        $students = collect(range(1, 8))->map(fn (int $index) => StudentProfile::factory()->create([
            'first_name' => 'Estudiante',
            'last_name' => sprintf('Optimizada %02d', $index),
            'general_status' => 'activo',
        ]));
        $target = $students->first();
        PsychologyCase::factory()->create(['student_profile_id' => $target->id, 'responsible_user_id' => $psychologist->id, 'status' => 'open']);
        PsychologyReferral::factory()->create(['student_profile_id' => $target->id, 'referred_by_user_id' => $psychologist->id, 'status' => 'submitted']);
        Sanctum::actingAs($superAdmin);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->getJson('/api/psychology/students')->assertOk()->assertJsonCount(8, 'data');
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $targetData = collect($response->json('data'))->firstWhere('id', $target->id);
        $this->assertTrue($targetData['active_case']);
        $this->assertSame(1, $targetData['pending_referrals']);
        $this->assertLessThanOrEqual(8, $queryCount, 'La búsqueda no debe ejecutar consultas por cada estudiante.');
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

    public function test_historical_activity_can_predate_case_opening_and_case_export_is_audited(): void
    {
        $psychologist = $this->userWithRole('psicologo');
        $case = PsychologyCase::factory()->create([
            'responsible_user_id' => $psychologist->id,
            'opened_at' => now(),
            'code' => 'PSI-2026-000777',
        ]);
        $historicalDate = now()->subYears(2)->toDateString();
        Sanctum::actingAs($psychologist);

        $this->postJson("/api/psychology/cases/{$case->id}/activities", [
            'type' => 'case_review',
            'activity_on' => $historicalDate,
            'objective' => 'Incorporar antecedente histórico al expediente.',
            'institutional_summary' => 'Actuación realizada antes de la apertura administrativa del caso.',
            'visibility' => 'psychology_team',
            'status' => 'finalized',
        ])->assertCreated()
            ->assertJsonPath('data.activity_on', $historicalDate);

        $this->postJson("/api/psychology/cases/{$case->id}/export")
            ->assertOk()
            ->assertJsonPath('data.code', 'PSI-2026-000777')
            ->assertJsonPath('data.activities.0.activity_on', $historicalDate)
            ->assertJsonPath('data.activities.0.institutional_summary', 'Actuación realizada antes de la apertura administrativa del caso.');

        $this->assertDatabaseHas('psychology_audit_events', [
            'action' => 'case.pdf_exported',
            'auditable_id' => $case->id,
            'user_id' => $psychologist->id,
        ]);
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
