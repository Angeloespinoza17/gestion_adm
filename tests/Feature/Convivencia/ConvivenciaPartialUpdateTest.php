<?php

namespace Tests\Feature\Convivencia;

use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaComplaint;
use App\Models\Convivencia\ConvivenciaDailyLog;
use App\Models\Convivencia\ConvivenciaDerivation;
use App\Models\Convivencia\ConvivenciaInterview;
use App\Models\Convivencia\ConvivenciaMeasure;
use App\Models\Convivencia\ConvivenciaPlan;
use App\Models\Convivencia\ConvivenciaSociogram;
use App\Models\User;
use Database\Seeders\ConvivenciaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConvivenciaPartialUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_updates_preserve_omitted_optional_fields_and_nested_collections(): void
    {
        $user = $this->seedAndActAsSuperAdmin();

        $plan = ConvivenciaPlan::query()->with('actions')->firstOrFail();
        $plan->forceFill([
            'specific_objectives' => ['Objetivo que debe conservarse.'],
            'resources_required' => 'Recursos previamente definidos.',
            'observations' => 'Observación que luego se limpiará de forma explícita.',
        ])->save();
        $planActionIds = $plan->actions()->orderBy('id')->pluck('id')->all();

        $this->putJson("/api/convivencia/plans/{$plan->id}", [
            'revision' => $plan->revision,
            'name' => 'Plan actualizado solo en sus escalares obligatorios',
            'general_objective' => $plan->general_objective,
            'status' => $plan->status,
        ])->assertOk();

        $plan->refresh();
        $this->assertSame(['Objetivo que debe conservarse.'], $plan->specific_objectives);
        $this->assertSame('Recursos previamente definidos.', $plan->resources_required);
        $this->assertSame($planActionIds, $plan->actions()->orderBy('id')->pluck('id')->all());

        $case = ConvivenciaCase::query()->with('people')->firstOrFail();
        $case->forceFill([
            'background' => 'Antecedente reservado que debe conservarse.',
            'internal_notes' => 'Nota interna persistente.',
        ])->save();
        $casePeopleIds = $case->people()->orderBy('id')->pluck('id')->all();
        $caseContext = $case->only(['academic_year_id', 'course_section_id', 'student_profile_id']);

        $this->putJson("/api/convivencia/cases/{$case->id}", [
            'classification_item_id' => $case->classification_item_id,
            'criticality_item_id' => $case->criticality_item_id,
            'responsible_user_id' => $case->responsible_user_id,
            'opened_at' => $case->opened_at->format('Y-m-d H:i:s'),
            'origin' => $case->origin,
            'initial_report' => 'Relato escalar actualizado manteniendo los antecedentes asociados.',
        ])->assertOk();

        $case->refresh();
        $this->assertSame($caseContext, $case->only(array_keys($caseContext)));
        $this->assertSame('Antecedente reservado que debe conservarse.', $case->background);
        $this->assertSame('Nota interna persistente.', $case->internal_notes);
        $this->assertSame($casePeopleIds, $case->people()->orderBy('id')->pluck('id')->all());

        $complaint = ConvivenciaComplaint::query()->whereNotNull('case_id')->firstOrFail();
        $complaint->forceFill([
            'contact_email' => 'contacto.conservado@example.test',
            'contact_phone' => '+56912345678',
            'received_at' => '2026-04-10 09:15:00',
            'happened_at' => '2026-04-09 14:30:00',
            'involved_snapshot' => [[
                'full_name' => 'Persona involucrada conservada',
                'role_type' => 'testigo',
            ]],
            'admissibility_result' => 'Resultado de admisibilidad previo.',
        ])->save();
        $complaintContext = $complaint->only([
            'academic_year_id',
            'course_section_id',
            'affected_student_id',
            'case_id',
        ]);

        $this->putJson("/api/convivencia/complaints/{$complaint->id}", [
            'complainant_type' => $complaint->complainant_type,
            'report_text' => 'Relato actualizado sin reenviar contactos ni personas involucradas.',
            'status' => $complaint->status,
        ])->assertOk();

        $complaint->refresh();
        $this->assertSame($complaintContext, $complaint->only(array_keys($complaintContext)));
        $this->assertSame('contacto.conservado@example.test', $complaint->contact_email);
        $this->assertSame('+56912345678', $complaint->contact_phone);
        $this->assertSame('2026-04-10 09:15:00', $complaint->received_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-09 14:30:00', $complaint->happened_at->format('Y-m-d H:i:s'));
        $this->assertSame('Persona involucrada conservada', $complaint->involved_snapshot[0]['full_name']);
        $this->assertSame('Resultado de admisibilidad previo.', $complaint->admissibility_result);

        $derivation = ConvivenciaDerivation::query()->firstOrFail();
        $derivation->forceFill([
            'external_contact_name' => 'Contacto de red conservado',
            'external_contact_email' => 'red@example.test',
            'external_contact_phone' => '+56987654321',
            'sent_at' => '2026-04-11 10:00:00',
            'response_due_at' => '2026-04-20 12:00:00',
            'responded_at' => '2026-04-18 11:00:00',
            'response_text' => 'Respuesta previa de la derivación.',
        ])->save();
        $derivationContext = $derivation->only(['case_id', 'academic_year_id', 'course_section_id', 'student_profile_id']);

        $this->putJson("/api/convivencia/derivations/{$derivation->id}", [
            'scope' => $derivation->scope,
            'status' => $derivation->status,
            'priority_level' => $derivation->priority_level,
            'confidentiality_level' => $derivation->confidentiality_level,
            'derived_at' => $derivation->derived_at->format('Y-m-d H:i:s'),
            'motive' => 'Motivo escalar actualizado sin reenviar contexto ni contactos.',
        ])->assertOk();

        $derivation->refresh();
        $this->assertSame($derivationContext, $derivation->only(array_keys($derivationContext)));
        $this->assertSame('Contacto de red conservado', $derivation->external_contact_name);
        $this->assertSame('red@example.test', $derivation->external_contact_email);
        $this->assertSame('+56987654321', $derivation->external_contact_phone);
        $this->assertSame('2026-04-11 10:00:00', $derivation->sent_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-20 12:00:00', $derivation->response_due_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-18 11:00:00', $derivation->responded_at->format('Y-m-d H:i:s'));
        $this->assertSame('Respuesta previa de la derivación.', $derivation->response_text);

        $interview = ConvivenciaInterview::query()->with('participants')->firstOrFail();
        $interview->forceFill([
            'agreements' => 'Acuerdos que deben conservarse.',
            'commitments' => 'Compromisos que deben conservarse.',
            'follow_up_date' => '2026-05-15',
            'internal_notes' => 'Notas internas de la entrevista.',
        ])->save();
        $participantIds = $interview->participants()->orderBy('id')->pluck('id')->all();
        $interviewContext = $interview->only(['case_id', 'student_profile_id', 'course_section_id']);

        $interviewPayload = [
            'responsible_user_id' => $interview->responsible_user_id,
            'interview_at' => $interview->interview_at->format('Y-m-d H:i:s'),
            'motive' => 'Motivo actualizado sin reenviar participantes.',
            'follow_up_status' => $interview->follow_up_status,
        ];
        if ($interview->interview_type_item_id) {
            $interviewPayload['interview_type_item_id'] = $interview->interview_type_item_id;
        } else {
            $interviewPayload['interview_type_label'] = $interview->interview_type_label;
        }

        $this->putJson("/api/convivencia/interviews/{$interview->id}", $interviewPayload)->assertOk();

        $interview->refresh();
        $this->assertSame($interviewContext, $interview->only(array_keys($interviewContext)));
        $this->assertSame('Acuerdos que deben conservarse.', $interview->agreements);
        $this->assertSame('Compromisos que deben conservarse.', $interview->commitments);
        $this->assertSame('2026-05-15', $interview->follow_up_date->format('Y-m-d'));
        $this->assertSame('Notas internas de la entrevista.', $interview->internal_notes);
        $this->assertSame($participantIds, $interview->participants()->orderBy('id')->pluck('id')->all());

        $measure = ConvivenciaMeasure::query()->whereIn('status', ['cumplida', 'cerrada'])->firstOrFail();
        $measure->forceFill([
            'evidence_summary' => 'Evidencia que debe conservarse.',
            'student_reflection' => 'Reflexión que debe conservarse.',
            'repair_action' => 'Acción reparatoria que debe conservarse.',
            'closure_notes' => 'Cierre que debe conservarse.',
            'closed_at' => '2026-04-25 16:00:00',
        ])->save();
        $measureContext = $measure->only(['case_id', 'student_profile_id', 'course_section_id']);

        $this->putJson("/api/convivencia/measures/{$measure->id}", [
            'responsible_user_id' => $measure->responsible_user_id,
            'description' => 'Descripción escalar actualizada sin reenviar evidencia ni cierre.',
            'training_objective' => $measure->training_objective,
            'assigned_at' => $measure->assigned_at->format('Y-m-d H:i:s'),
            'due_at' => $measure->due_at->format('Y-m-d H:i:s'),
            'status' => $measure->status,
        ])->assertOk();

        $measure->refresh();
        $this->assertSame($measureContext, $measure->only(array_keys($measureContext)));
        $this->assertSame('Evidencia que debe conservarse.', $measure->evidence_summary);
        $this->assertSame('Reflexión que debe conservarse.', $measure->student_reflection);
        $this->assertSame('Acción reparatoria que debe conservarse.', $measure->repair_action);
        $this->assertSame('Cierre que debe conservarse.', $measure->closure_notes);
        $this->assertSame('2026-04-25 16:00:00', $measure->closed_at->format('Y-m-d H:i:s'));

        $dailyLog = ConvivenciaDailyLog::query()->whereNotNull('case_id')->firstOrFail();
        $dailyLog->forceFill([
            'involved_snapshot' => [['full_name' => 'Testigo que debe conservarse']],
            'guardian_informed' => true,
            'guardian_contact_note' => 'Contacto con apoderado que debe conservarse.',
            'immediate_action' => 'Acción inmediata que debe conservarse.',
        ])->save();
        $dailyLogContext = $dailyLog->only([
            'case_id',
            'generated_derivation_id',
            'academic_year_id',
            'course_section_id',
            'student_profile_id',
        ]);

        $dailyLogPayload = [
            'inspector_user_id' => $dailyLog->inspector_user_id,
            'happened_at' => $dailyLog->happened_at->format('Y-m-d H:i:s'),
            'description' => 'Descripción actualizada sin reenviar personas ni contacto.',
            'status' => $dailyLog->status,
        ];
        if ($dailyLog->daily_log_type_item_id) {
            $dailyLogPayload['daily_log_type_item_id'] = $dailyLog->daily_log_type_item_id;
        } else {
            $dailyLogPayload['daily_log_type_label'] = $dailyLog->daily_log_type_label;
        }

        $this->putJson("/api/convivencia/daily-logs/{$dailyLog->id}", $dailyLogPayload)->assertOk();

        $dailyLog->refresh();
        $this->assertSame($dailyLogContext, $dailyLog->only(array_keys($dailyLogContext)));
        $this->assertSame('Testigo que debe conservarse', $dailyLog->involved_snapshot[0]['full_name']);
        $this->assertTrue($dailyLog->guardian_informed);
        $this->assertSame('Contacto con apoderado que debe conservarse.', $dailyLog->guardian_contact_note);
        $this->assertSame('Acción inmediata que debe conservarse.', $dailyLog->immediate_action);

        $sociogram = ConvivenciaSociogram::query()->with(['questions', 'answers'])->firstOrFail();
        $questionIds = $sociogram->questions()->orderBy('id')->pluck('id')->all();
        $answerIds = $sociogram->answers()->orderBy('id')->pluck('id')->all();
        $sociogramContext = $sociogram->only(['academic_year_id', 'course_section_id']);
        $matrixSummary = $sociogram->matrix_summary;
        $resultSummary = $sociogram->result_summary;

        $this->putJson("/api/convivencia/sociograms/{$sociogram->id}", [
            'course_section_id' => $sociogram->course_section_id,
            'title' => 'Sociograma actualizado sin reenviar su estructura',
            'applied_on' => $sociogram->applied_on->format('Y-m-d'),
            'status' => $sociogram->status,
            'confidentiality_level' => $sociogram->confidentiality_level,
        ])->assertOk();

        $sociogram->refresh();
        $this->assertSame($sociogramContext, $sociogram->only(array_keys($sociogramContext)));
        $this->assertSame($matrixSummary, $sociogram->matrix_summary);
        $this->assertSame($resultSummary, $sociogram->result_summary);
        $this->assertSame($questionIds, $sociogram->questions()->orderBy('id')->pluck('id')->all());
        $this->assertSame($answerIds, $sociogram->answers()->orderBy('id')->pluck('id')->all());

        $this->putJson("/api/convivencia/plans/{$plan->id}", [
            'revision' => $plan->revision,
            'name' => $plan->name,
            'general_objective' => $plan->general_objective,
            'status' => $plan->status,
            'observations' => null,
        ])->assertOk();

        $this->assertNull($plan->fresh()->observations);
        $this->assertSame($planActionIds, $plan->actions()->orderBy('id')->pluck('id')->all());
    }

    private function seedAndActAsSuperAdmin(): User
    {
        $this->seed(ConvivenciaSeeder::class);

        $user = User::query()
            ->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin'))
            ->firstOrFail();

        Sanctum::actingAs($user);

        return $user;
    }
}
