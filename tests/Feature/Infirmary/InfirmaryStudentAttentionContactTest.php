<?php

namespace Tests\Feature\Infirmary;

use App\Models\Infirmary\InfirmaryAttentionTreatment;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InfirmaryStudentAttentionContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super administrador', 'active' => true],
        );
        $user->roles()->attach($role);

        Sanctum::actingAs($user);
    }

    public function test_attention_stores_phone_call_and_exposes_primary_and_backup_guardian_contacts(): void
    {
        $student = StudentProfile::query()->create([
            'first_name' => 'Josefa',
            'last_name' => 'Contreras',
            'rut' => '26111222-3',
            'guardian_name' => 'Andrea Contreras',
            'guardian_relationship' => 'Madre',
            'guardian_phone' => '+56 9 1111 2222',
            'guardian_email' => 'andrea@example.test',
            'guardian_backup_name' => 'Luis Soto',
            'guardian_backup_relationship' => 'Abuelo',
            'guardian_backup_phone' => '+56 9 3333 4444',
            'guardian_backup_email' => 'luis@example.test',
        ]);

        $response = $this->postJson('/api/infirmary/attentions', [
            'student_profile_id' => $student->id,
            'attention_category' => 'dolor_cabeza',
            'occurred_at' => now()->subMinutes(10)->format('Y-m-d H:i:s'),
            'attended_at' => now()->subMinutes(5)->format('Y-m-d H:i:s'),
            'accompanied_by_type' => 'sin_acompanante',
            'consultation_reason' => 'Dolor de cabeza persistente',
            'priority' => 'media',
            'status' => 'abierta',
            'calls' => [[
                'called_at' => now()->subMinutes(5)->format('Y-m-d H:i:s'),
                'person_contacted' => 'Andrea Contreras',
                'relationship' => 'Madre',
                'phone_number' => '+56 9 1111 2222',
                'call_status' => 'contesto',
                'conversation_summary' => 'Se informa el malestar y la apoderada confirma que retirará a la estudiante.',
            ]],
        ])->assertCreated();

        $attentionId = $response->json('data.id');

        $response
            ->assertJsonPath('data.calls.0.person_contacted', 'Andrea Contreras')
            ->assertJsonPath('data.calls.0.call_status', 'contesto')
            ->assertJsonPath('data.calls.0.conversation_summary', 'Se informa el malestar y la apoderada confirma que retirará a la estudiante.')
            ->assertJsonPath('data.student.guardian_email', 'andrea@example.test')
            ->assertJsonPath('data.student.guardian_backup_email', 'luis@example.test');

        $this->getJson("/api/infirmary/attentions/{$attentionId}")
            ->assertOk()
            ->assertJsonPath('data.student.guardian_name', 'Andrea Contreras')
            ->assertJsonPath('data.student.guardian_relationship', 'Madre')
            ->assertJsonPath('data.student.guardian_phone', '+56 9 1111 2222')
            ->assertJsonPath('data.student.guardian_email', 'andrea@example.test')
            ->assertJsonPath('data.student.guardian_backup_name', 'Luis Soto')
            ->assertJsonPath('data.student.guardian_backup_relationship', 'Abuelo')
            ->assertJsonPath('data.student.guardian_backup_phone', '+56 9 3333 4444')
            ->assertJsonPath('data.student.guardian_backup_email', 'luis@example.test');
    }

    public function test_quick_attention_stores_minor_care_actions_in_the_regular_clinical_record(): void
    {
        $student = StudentProfile::query()->create([
            'first_name' => 'Martina',
            'last_name' => 'Silva',
            'rut' => '27111222-4',
        ]);

        $attendedAt = now()->subMinute()->format('Y-m-d H:i:s');

        $response = $this->postJson('/api/infirmary/attentions', [
            'student_profile_id' => $student->id,
            'attention_category' => 'dolor_cabeza',
            'occurred_at' => $attendedAt,
            'attended_at' => $attendedAt,
            'accompanied_by_type' => 'sin_acompanante',
            'consultation_reason' => 'Dolor de cabeza',
            'logbook' => 'Atención rápida: Dar agua, Reposo breve · Resultado: Vuelve a sala',
            'attention_duration_minutes' => 5,
            'priority' => 'baja',
            'status' => 'finalizada',
            'treatments' => [[
                'treatment_categories' => ['fisico'],
                'treatment_types' => ['hidratacion_oral', 'reposo', 'observacion_breve'],
                'emotional_support_required' => false,
                'notes' => 'Atención rápida · Vuelve a sala',
            ]],
        ])->assertCreated();

        $attentionId = $response->json('data.id');

        $response
            ->assertJsonPath('data.status', 'finalizada')
            ->assertJsonPath('data.priority', 'baja')
            ->assertJsonPath('data.attention_duration_minutes', 5)
            ->assertJsonPath('data.treatments.0.treatment_types.0', 'hidratacion_oral')
            ->assertJsonPath('data.treatments.0.treatment_types.1', 'reposo')
            ->assertJsonPath('data.treatments.0.treatment_types.2', 'observacion_breve');

        $treatment = InfirmaryAttentionTreatment::query()
            ->where('attention_id', $attentionId)
            ->firstOrFail();

        $this->assertSame(['hidratacion_oral', 'reposo', 'observacion_breve'], $treatment->treatment_types);
        $this->assertDatabaseHas('infirmary_attentions', [
            'id' => $attentionId,
            'student_profile_id' => $student->id,
            'status' => 'finalizada',
            'attention_duration_minutes' => 5,
        ]);
    }

    public function test_student_attention_requires_and_stores_the_mental_health_categorization(): void
    {
        $this->getJson('/api/infirmary/catalogs')
            ->assertOk()
            ->assertJsonFragment(['value' => 'salud_mental', 'label' => 'Salud mental'])
            ->assertJsonFragment(['value' => 'autolesion', 'label' => 'Autolesión'])
            ->assertJsonFragment(['value' => 'contencion', 'label' => 'Contención'])
            ->assertJsonFragment(['value' => 'ingesta_medicamentos', 'label' => 'Ingesta de medicamentos'])
            ->assertJsonFragment(['value' => 'corte', 'label' => 'Corte'])
            ->assertJsonFragment(['value' => 'contusion', 'label' => 'Contusión'])
            ->assertJsonFragment(['value' => 'herida_abrasiva', 'label' => 'Herida abrasiva']);

        $student = StudentProfile::query()->create([
            'first_name' => 'Antonia',
            'last_name' => 'Morales',
            'rut' => '28111222-5',
        ]);
        $payload = [
            'student_profile_id' => $student->id,
            'attention_category' => 'salud_mental',
            'occurred_at' => now()->subMinutes(10)->format('Y-m-d H:i:s'),
            'attended_at' => now()->subMinutes(5)->format('Y-m-d H:i:s'),
            'accompanied_by_type' => 'sin_acompanante',
            'consultation_reason' => 'Evaluación y contención inicial en Enfermería.',
            'priority' => 'alta',
            'status' => 'abierta',
        ];

        $this->postJson('/api/infirmary/attentions', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mental_health_event_type');

        $this->postJson('/api/infirmary/attentions', [
            ...$payload,
            'mental_health_event_type' => 'autolesion',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('self_harm_injury_type');

        $response = $this->postJson('/api/infirmary/attentions', [
            ...$payload,
            'mental_health_event_type' => 'autolesion',
            'self_harm_injury_type' => 'corte',
        ])->assertCreated()
            ->assertJsonPath('data.attention_category', 'salud_mental')
            ->assertJsonPath('data.mental_health_event_type', 'autolesion')
            ->assertJsonPath('data.self_harm_injury_type', 'corte');

        $attentionId = $response->json('data.id');

        $this->putJson("/api/infirmary/attentions/{$attentionId}", [
            ...$payload,
            'mental_health_event_type' => 'contencion',
            'self_harm_injury_type' => 'herida_abrasiva',
        ])->assertOk()
            ->assertJsonPath('data.mental_health_event_type', 'contencion')
            ->assertJsonPath('data.self_harm_injury_type', null);

        $this->putJson("/api/infirmary/attentions/{$attentionId}", [
            ...$payload,
            'mental_health_event_type' => 'ingesta_medicamentos',
        ])->assertOk()
            ->assertJsonPath('data.mental_health_event_type', 'ingesta_medicamentos')
            ->assertJsonPath('data.self_harm_injury_type', null);
    }
}
