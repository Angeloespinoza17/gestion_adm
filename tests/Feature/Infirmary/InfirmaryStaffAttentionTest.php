<?php

namespace Tests\Feature\Infirmary;

use App\Models\Infirmary\InfirmaryAttention;
use App\Models\Infirmary\InfirmaryMedication;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InfirmaryStaffAttentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->create([
            'name' => 'Super administrador',
            'slug' => 'super_admin',
            'active' => true,
        ]);
        $user->roles()->attach($role);

        Sanctum::actingAs($user);
    }

    public function test_staff_attention_is_stored_and_kept_out_of_student_routes(): void
    {
        $staff = Staff::query()->create([
            'full_name' => 'Carolina Muñoz Soto',
            'rut' => '12345678-5',
            'status' => 'activo',
            'active' => true,
        ]);

        $response = $this->postJson('/api/infirmary/staff-attentions', $this->payload($staff));

        $response
            ->assertCreated()
            ->assertJsonPath('data.subject_type', InfirmaryAttention::SUBJECT_STAFF)
            ->assertJsonPath('data.staff_id', $staff->id)
            ->assertJsonPath('data.student_profile_id', null)
            ->assertJsonPath('data.staff_full_name_snapshot', 'Carolina Muñoz Soto')
            ->assertJsonMissingPath('data.status')
            ->assertJsonMissingPath('data.attention_duration_minutes')
            ->assertJsonMissingPath('data.finalized_at');

        $attentionId = $response->json('data.id');

        $this->getJson('/api/infirmary/staff-attentions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.staff.id', $staff->id);

        $this->getJson('/api/infirmary/attentions')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/infirmary/attentions/{$attentionId}")
            ->assertNotFound();
    }

    public function test_student_attention_is_not_available_through_staff_routes(): void
    {
        $student = StudentProfile::query()->create([
            'first_name' => 'Ana',
            'last_name' => 'Rojas',
            'rut' => '21111111-1',
        ]);

        $attention = InfirmaryAttention::query()->create([
            'subject_type' => InfirmaryAttention::SUBJECT_STUDENT,
            'student_profile_id' => $student->id,
            'attended_at' => now()->subMinutes(5),
            'occurred_at' => now()->subMinutes(10),
            'student_full_name_snapshot' => 'Ana Rojas',
            'student_rut_snapshot' => '21111111-1',
            'attention_category' => 'dolor_cabeza',
            'accompanied_by_type' => 'sin_acompanante',
            'consultation_reason' => 'Dolor de cabeza',
            'priority' => 'media',
            'status' => 'abierta',
        ]);

        $this->getJson('/api/infirmary/staff-attentions')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/infirmary/staff-attentions/{$attention->id}")
            ->assertNotFound();
    }

    public function test_medication_given_to_staff_keeps_staff_traceability_and_decreases_stock(): void
    {
        $staff = Staff::query()->create([
            'full_name' => 'Pedro González',
            'rut' => '98765432-1',
            'status' => 'activo',
            'active' => true,
        ]);
        $medication = InfirmaryMedication::query()->create([
            'inventory_type' => InfirmaryMedication::INVENTORY_TYPE_MEDICATION,
            'source_type' => 'school',
            'name' => 'Paracetamol',
            'unit' => 'comprimido',
            'current_stock' => 10,
            'minimum_stock' => 2,
            'active' => true,
        ]);

        $payload = $this->payload($staff);
        $payload['treatments'] = [[
            'treatment_categories' => ['fisico'],
            'treatment_types' => ['administracion_medicamento'],
            'medication_id' => $medication->id,
            'medication_quantity' => 1,
        ]];

        $response = $this->postJson('/api/infirmary/staff-attentions', $payload)
            ->assertCreated();

        $this->assertDatabaseHas('infirmary_medication_administrations', [
            'attention_id' => $response->json('data.id'),
            'student_profile_id' => null,
            'staff_id' => $staff->id,
            'medication_id' => $medication->id,
            'quantity_administered' => 1,
        ]);
        $this->assertDatabaseHas('infirmary_medications', [
            'id' => $medication->id,
            'current_stock' => 9,
        ]);
    }

    public function test_staff_attention_ignores_workflow_status_and_duration(): void
    {
        $staff = Staff::query()->create([
            'full_name' => 'Marcela Soto Díaz',
            'rut' => '11222333-4',
            'status' => 'activo',
            'active' => true,
        ]);

        $payload = $this->payload($staff);
        $payload['status'] = 'finalizada';
        $payload['attention_duration_minutes'] = 90;

        $response = $this->postJson('/api/infirmary/staff-attentions', $payload)
            ->assertCreated()
            ->assertJsonMissingPath('data.status')
            ->assertJsonMissingPath('data.attention_duration_minutes')
            ->assertJsonMissingPath('data.finalized_at');

        $this->assertDatabaseHas('infirmary_attentions', [
            'id' => $response->json('data.id'),
            'subject_type' => InfirmaryAttention::SUBJECT_STAFF,
            'status' => 'abierta',
            'attention_duration_minutes' => null,
            'finalized_at' => null,
        ]);
    }

    public function test_student_and_staff_attentions_use_independent_correlatives(): void
    {
        $staff = Staff::query()->create([
            'full_name' => 'José Pérez Silva',
            'rut' => '15555111-2',
            'status' => 'activo',
            'active' => true,
        ]);
        $student = StudentProfile::query()->create([
            'first_name' => 'María',
            'last_name' => 'López',
            'rut' => '21111222-3',
        ]);

        $studentPayload = [
            'student_profile_id' => $student->id,
            'attention_category' => 'accidente_menor',
            'accident_location_type' => 'trayecto',
            'occurred_at' => now()->subMinutes(10)->format('Y-m-d H:i:s'),
            'attended_at' => now()->subMinutes(5)->format('Y-m-d H:i:s'),
            'accompanied_by_type' => 'sin_acompanante',
            'consultation_reason' => 'Control preventivo',
            'priority' => 'media',
            'status' => 'abierta',
            'treatments' => [],
            'referrals' => [],
            'calls' => [],
            'follow_ups' => [],
        ];

        $this->postJson('/api/infirmary/attentions', $studentPayload)
            ->assertCreated()
            ->assertJsonPath('data.correlative_number', 1);
        $this->postJson('/api/infirmary/staff-attentions', $this->payload($staff))
            ->assertCreated()
            ->assertJsonPath('data.correlative_number', 1);
        $this->postJson('/api/infirmary/attentions', $studentPayload)
            ->assertCreated()
            ->assertJsonPath('data.correlative_number', 2);
        $this->postJson('/api/infirmary/staff-attentions', $this->payload($staff))
            ->assertCreated()
            ->assertJsonPath('data.correlative_number', 2);

        $this->getJson('/api/infirmary/attentions?school_insurance=1&search=00002')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject_type', InfirmaryAttention::SUBJECT_STUDENT)
            ->assertJsonPath('data.0.correlative_number', 2);
    }

    private function payload(Staff $staff): array
    {
        return [
            'staff_id' => $staff->id,
            'attention_category' => 'control_signos_vitales',
            'occurred_at' => now()->subMinutes(10)->format('Y-m-d H:i:s'),
            'attended_at' => now()->subMinutes(5)->format('Y-m-d H:i:s'),
            'accompanied_by_type' => 'sin_acompanante',
            'consultation_reason' => 'Control preventivo de presión arterial',
            'priority' => 'media',
            'treatments' => [[
                'treatment_categories' => ['csv'],
                'treatment_types' => [],
                'blood_pressure' => '120/80',
                'pulse' => 72,
            ]],
            'referrals' => [],
            'calls' => [],
            'follow_ups' => [],
        ];
    }
}
