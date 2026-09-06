<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\Security\SecurityIncident;
use App\Models\Security\SecurityIncidentStatus;
use App\Models\Security\SecurityRound;
use App\Models\Security\SecurityRoundSector;
use App\Models\Security\SecurityShift;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-05 02:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_exposes_a_recent_night_logbook_and_useful_shift_metrics(): void
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'active' => true],
        );
        $user->roles()->attach($role);
        Sanctum::actingAs($user);

        $staff = Staff::query()->create([
            'full_name' => 'José Campos',
            'rut' => '17.765.432-1',
            'status' => 'activo',
            'active' => true,
        ]);
        $shift = SecurityShift::query()->create([
            'staff_id' => $staff->id,
            'scheduled_start_at' => '2026-09-04 22:00:00',
            'scheduled_end_at' => '2026-09-05 07:00:00',
            'started_at' => '2026-09-04 22:00:00',
            'status' => SecurityShift::STATUS_EN_CURSO,
            'coverage_label' => 'Todo el colegio',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $round = SecurityRound::query()->create([
            'security_shift_id' => $shift->id,
            'recorded_by_user_id' => $user->id,
            'round_number' => 1,
            'recorded_at' => '2026-09-05 01:15:00',
            'overall_status' => SecurityRound::STATUS_REQUIERE_ATENCION,
            'observations' => 'Se aisló un sector preventivamente.',
            'nochero_confirmation_name' => 'José Campos',
            'act_number' => 'ACT-NOCHE-001',
            'act_generated_at' => '2026-09-05 01:15:00',
        ]);
        SecurityRoundSector::query()->create([
            'security_round_id' => $round->id,
            'sector_name' => 'Laboratorio',
            'sector_state' => 'riesgo_detectado',
            'display_order' => 1,
        ]);
        $resolvedStatus = SecurityIncidentStatus::query()->where('code', 'resuelta')->firstOrFail();
        SecurityIncident::query()->forceCreate([
            'security_shift_id' => $shift->id,
            'security_round_id' => $round->id,
            'reported_by_user_id' => $user->id,
            'status_id' => $resolvedStatus->id,
            'priority' => SecurityIncident::PRIORITY_ALTA,
            'title' => 'Acceso observado',
            'description' => 'Se revisó y aseguró el acceso.',
            'sector_name' => 'Laboratorio',
            'created_at' => '2026-09-05 01:15:00',
            'responded_at' => '2026-09-05 01:45:00',
            'resolved_at' => '2026-09-05 01:45:00',
        ]);

        $this->getJson('/api/security/dashboard')
            ->assertOk()
            ->assertJsonPath('totals.active_shifts', 1)
            ->assertJsonPath('totals.rounds_today', 1)
            ->assertJsonPath('totals.attention_rounds', 1)
            ->assertJsonPath('totals.average_response_minutes', 30)
            ->assertJsonPath('recent_rounds.0.act_number', 'ACT-NOCHE-001')
            ->assertJsonPath('recent_rounds.0.shift.staff.full_name', 'José Campos')
            ->assertJsonPath('recent_rounds.0.sectors_count', 1)
            ->assertJsonPath('recent_rounds.0.incidents_count', 1)
            ->assertJsonPath('recent_rounds.0.pending_incidents_count', 0);
    }
}
