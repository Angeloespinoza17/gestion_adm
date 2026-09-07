<?php

namespace Tests\Feature;

use App\Models\ApoyoProfesional\ApoyoAtencion;
use App\Models\ApoyoProfesional\ApoyoDerivacion;
use App\Models\ApoyoProfesional\ApoyoProfesionalProfile;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\ApoyoProfesional\ApoyoProfesionalAccessService;
use App\Services\ApoyoProfesional\ApoyoProfesionalDerivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApoyoProfesionalNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_derivation_notifies_destination_and_response_notifies_origin_without_sensitive_detail(): void
    {
        $origin = User::factory()->create(['active' => true]);
        $destination = User::factory()->create(['active' => true]);
        $student = StudentProfile::factory()->create();
        $attention = ApoyoAtencion::query()->create([
            'student_profile_id' => $student->id,
            'attended_at' => now(),
            'professional_role_name' => 'Orientación',
            'professional_area_slug' => 'orientacion',
            'professional_area_name' => 'Orientación',
            'student_full_name_snapshot' => 'Identidad protegida',
            'modality' => 'presencial',
            'origin' => 'observacion_profesional',
            'priority_level' => 'alta',
            'confidentiality_level' => 'confidencial',
            'reason_summary' => 'Antecedente reservado de prueba.',
            'status' => 'abierta',
            'created_by' => $origin->id,
            'updated_by' => $origin->id,
        ]);
        $service = app(ApoyoProfesionalDerivationService::class);

        $derivation = $service->store([
            'attention_id' => $attention->id,
            'destination_user_id' => $destination->id,
            'destination_area_slug' => 'convivencia_escolar',
            'destination_area_name' => 'Convivencia Escolar',
            'urgency_level' => 'urgente',
            'confidentiality_level' => 'confidencial',
            'reason' => 'Detalle sensible que no debe quedar en la notificación.',
            'description' => 'Relato profesional reservado.',
            'derived_at' => now()->toDateTimeString(),
        ], $origin);

        $createdNotice = $destination->notifications()->firstOrFail();
        $this->assertSame('apoyo.derivation.created', $createdNotice->data['event_type']);
        $this->assertSame('critica', $createdNotice->data['priority']);
        $this->assertSame($derivation->id, $createdNotice->data['event']['resource']['id']);
        $this->assertStringNotContainsString('Detalle sensible', $createdNotice->data['message']);

        $service->respond($derivation, [
            'status' => 'aceptada',
            'destination_response' => 'Respuesta profesional reservada.',
        ], $destination);

        $responseNotice = $origin->notifications()->firstOrFail();
        $this->assertSame('apoyo.derivation.responded', $responseNotice->data['event_type']);
        $this->assertSame('aceptada', $responseNotice->data['event']['context']['status']);
        $this->assertStringNotContainsString('Respuesta profesional reservada', $responseNotice->data['message']);
    }

    public function test_area_derivation_uses_active_professional_profiles_when_no_person_is_selected(): void
    {
        $origin = User::factory()->create(['active' => true]);
        $recipient = User::factory()->create(['active' => true]);
        ApoyoProfesionalProfile::query()->create([
            'user_id' => $recipient->id,
            'area_slug' => 'pie',
            'area_name' => 'PIE',
            'professional_role_name' => 'Coordinación PIE',
            'can_receive_derivations' => true,
            'active' => true,
        ]);
        $student = StudentProfile::factory()->create();
        $attention = ApoyoAtencion::query()->create([
            'student_profile_id' => $student->id,
            'attended_at' => now(),
            'professional_role_name' => 'Orientación',
            'professional_area_slug' => 'orientacion',
            'professional_area_name' => 'Orientación',
            'student_full_name_snapshot' => 'Identidad protegida',
            'modality' => 'presencial',
            'origin' => 'observacion_profesional',
            'priority_level' => 'media',
            'confidentiality_level' => 'reservada',
            'reason_summary' => 'Solicitud de apoyo.',
            'status' => 'abierta',
            'created_by' => $origin->id,
            'updated_by' => $origin->id,
        ]);

        $derivation = app(ApoyoProfesionalDerivationService::class)->store([
            'attention_id' => $attention->id,
            'destination_area_slug' => 'pie',
            'destination_area_name' => 'PIE',
            'urgency_level' => 'media',
            'reason' => 'Evaluar apoyos requeridos.',
            'derived_at' => now()->toDateTimeString(),
        ], $origin);

        $this->assertSame('apoyo.derivation.created', $recipient->notifications()->firstOrFail()->data['event_type']);
        $access = app(ApoyoProfesionalAccessService::class);
        $this->assertTrue($access->canViewDerivation($recipient, $derivation));
        $this->assertTrue($access->applyDerivationVisibility(
            ApoyoDerivacion::query(),
            $recipient,
        )->whereKey($derivation->id)->exists());
    }
}
