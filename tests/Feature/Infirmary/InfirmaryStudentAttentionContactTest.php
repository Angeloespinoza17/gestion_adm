<?php

namespace Tests\Feature\Infirmary;

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
}
