<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\Security\SecurityIncident;
use App\Models\Security\SecurityIncidentStatus;
use App\Models\Security\SecurityShift;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityIncidentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-05 03:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_incident_queue_returns_operational_summary_and_keeps_pending_filter_at_source(): void
    {
        $user = $this->superAdmin();
        Sanctum::actingAs($user);
        $shift = $this->shift($user);
        $pending = SecurityIncidentStatus::query()->where('code', 'pendiente')->firstOrFail();
        $resolved = SecurityIncidentStatus::query()->where('code', 'resuelta')->firstOrFail();

        SecurityIncident::query()->forceCreate([
            'security_shift_id' => $shift->id,
            'reported_by_user_id' => $user->id,
            'status_id' => $pending->id,
            'priority' => SecurityIncident::PRIORITY_CRITICA,
            'title' => 'Ventana forzada en laboratorio',
            'description' => 'Se detectó daño en el cierre y el sector quedó aislado.',
            'sector_name' => 'Laboratorio',
            'requires_immediate_attention' => true,
            'response_due_at' => '2026-09-05 02:00:00',
            'created_at' => '2026-09-05 01:10:00',
            'updated_at' => '2026-09-05 01:10:00',
        ]);

        SecurityIncident::query()->forceCreate([
            'security_shift_id' => $shift->id,
            'reported_by_user_id' => $user->id,
            'status_id' => $resolved->id,
            'current_responsible_user_id' => $user->id,
            'priority' => SecurityIncident::PRIORITY_MEDIA,
            'title' => 'Puerta lateral asegurada',
            'description' => 'La revisión de cierre fue completada.',
            'sector_name' => 'Acceso lateral',
            'requires_immediate_attention' => false,
            'response_due_at' => '2026-09-05 02:30:00',
            'resolved_at' => '2026-09-05 02:00:00',
            'created_at' => '2026-09-05 01:20:00',
            'updated_at' => '2026-09-05 02:00:00',
        ]);

        $this->getJson('/api/security/incidents?pending_only=1&per_page=15')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.open', 1)
            ->assertJsonPath('summary.critical', 1)
            ->assertJsonPath('summary.overdue', 1)
            ->assertJsonPath('summary.unassigned', 1)
            ->assertJsonPath('data.0.title', 'Ventana forzada en laboratorio')
            ->assertJsonPath('data.0.shift.staff.full_name', 'José Campos');

        $this->getJson('/api/security/incidents?pending_only=0&search=Puerta')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('summary.open', 0)
            ->assertJsonPath('summary.overdue', 0)
            ->assertJsonPath('summary.unassigned', 0)
            ->assertJsonPath('data.0.status.code', 'resuelta');
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['active' => true]);
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'active' => true],
        );
        $user->roles()->attach($role);

        return $user;
    }

    private function shift(User $user): SecurityShift
    {
        $staff = Staff::query()->create([
            'full_name' => 'José Campos',
            'rut' => '17.765.432-1',
            'status' => 'activo',
            'active' => true,
        ]);

        return SecurityShift::query()->create([
            'staff_id' => $staff->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'scheduled_start_at' => '2026-09-04 22:00:00',
            'scheduled_end_at' => '2026-09-05 07:00:00',
            'status' => SecurityShift::STATUS_EN_CURSO,
            'coverage_label' => 'Todo el colegio',
        ]);
    }
}
