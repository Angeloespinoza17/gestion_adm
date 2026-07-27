<?php

namespace Tests\Feature\Infirmary;

use App\Models\Infirmary\InfirmaryAttention;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InfirmarySchoolInsuranceSequenceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private StudentProfile $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['active' => true]);
        $role = Role::query()->create([
            'name' => 'Super administrador',
            'slug' => 'super_admin',
            'active' => true,
        ]);
        $this->user->roles()->attach($role);
        $this->student = StudentProfile::query()->create([
            'first_name' => 'María',
            'last_name' => 'López',
            'rut' => '21111222-3',
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_school_insurance_can_start_at_994_without_changing_attention_correlatives(): void
    {
        $this->putJson('/api/infirmary/school-insurance-sequence', [
            'next_number' => 994,
            'reason' => 'Continuidad con certificados físicos históricos.',
        ])
            ->assertOk()
            ->assertJsonPath('data.last_number', 993)
            ->assertJsonPath('data.next_number', 994);

        $regularAttention = $this->postJson(
            '/api/infirmary/attentions',
            $this->attentionPayload('dolor_cabeza'),
        )
            ->assertCreated()
            ->assertJsonPath('data.correlative_number', 1)
            ->assertJsonPath('data.school_insurance_number', null);

        $firstInsurance = $this->postJson(
            '/api/infirmary/attentions',
            $this->attentionPayload('accidente_menor'),
        )
            ->assertCreated()
            ->assertJsonPath('data.correlative_number', 2)
            ->assertJsonPath('data.school_insurance_number', 994);

        $this->postJson('/api/infirmary/attentions', $this->attentionPayload('otro'))
            ->assertCreated()
            ->assertJsonPath('data.correlative_number', 3)
            ->assertJsonPath('data.school_insurance_number', null);

        $this->postJson('/api/infirmary/attentions', $this->attentionPayload('accidente_mayor'))
            ->assertCreated()
            ->assertJsonPath('data.correlative_number', 4)
            ->assertJsonPath('data.school_insurance_number', 995);

        $this->assertDatabaseHas('infirmary_sequence_adjustments', [
            'sequence_key' => 'school_insurance',
            'previous_last_number' => 0,
            'new_last_number' => 993,
            'next_number' => 994,
            'changed_by' => $this->user->id,
        ]);
        $this->assertDatabaseHas('infirmary_attentions', [
            'id' => $regularAttention->json('data.id'),
            'correlative_number' => 1,
            'school_insurance_number' => null,
        ]);

        $this->getJson('/api/infirmary/attentions?school_insurance=1&search=00994')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $firstInsurance->json('data.id'))
            ->assertJsonPath('data.0.school_insurance_number', 994);
    }

    public function test_school_insurance_sequence_cannot_move_backwards_or_reuse_a_number(): void
    {
        $this->putJson('/api/infirmary/school-insurance-sequence', [
            'next_number' => 994,
            'reason' => 'Continuidad con certificados físicos históricos.',
        ])->assertOk();

        $attentionId = $this->postJson(
            '/api/infirmary/attentions',
            $this->attentionPayload('accidente_menor'),
        )
            ->assertCreated()
            ->assertJsonPath('data.school_insurance_number', 994)
            ->json('data.id');

        $this->putJson('/api/infirmary/school-insurance-sequence', [
            'next_number' => 994,
            'reason' => 'Intento de reutilización del correlativo.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('next_number');

        $this->assertDatabaseHas('infirmary_attentions', [
            'id' => $attentionId,
            'school_insurance_number' => 994,
        ]);

        $this->postJson('/api/infirmary/attentions', $this->attentionPayload('accidente_mayor'))
            ->assertCreated()
            ->assertJsonPath('data.school_insurance_number', 995);
    }

    private function attentionPayload(string $category): array
    {
        $isAccident = in_array($category, ['accidente_menor', 'accidente_mayor'], true);

        return [
            'student_profile_id' => $this->student->id,
            'attention_category' => $category,
            'accident_location_type' => $isAccident ? 'trayecto' : null,
            'occurred_at' => now()->subMinutes(10)->format('Y-m-d H:i:s'),
            'attended_at' => now()->subMinutes(5)->format('Y-m-d H:i:s'),
            'accompanied_by_type' => 'sin_acompanante',
            'consultation_reason' => $isAccident ? 'Accidente escolar' : 'Atención general',
            'accident_circumstance' => $isAccident ? 'Durante la jornada escolar' : null,
            'priority' => 'media',
            'status' => 'abierta',
            'treatments' => [],
            'referrals' => [],
            'calls' => [],
            'follow_ups' => [],
        ];
    }
}
