<?php

namespace Tests\Feature\Security;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Security\SecurityShift;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityShiftScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_manager_defines_only_one_weekly_schedule_per_nochero_and_can_update_its_days(): void
    {
        $manager = $this->superAdmin();
        $staff = $this->staff('Nochero Uno');
        Sanctum::actingAs($manager);

        $this->postJson('/api/security/shifts', $this->schedulePayload($staff, ['Monday', 'Wednesday']))
            ->assertCreated()
            ->assertJsonPath('data.weekdays.0', 'Monday');

        $this->postJson('/api/security/shifts', $this->schedulePayload($staff, ['Tuesday', 'Thursday']))
            ->assertOk()
            ->assertJsonPath('message', 'Días de trabajo actualizados correctamente.')
            ->assertJsonPath('data.weekdays.0', 'Tuesday');

        $this->assertDatabaseCount('security_shifts', 1);
        $this->assertDatabaseHas('security_shifts', [
            'staff_id' => $staff->id,
            'schedule_type' => SecurityShift::SCHEDULE_WEEKLY,
            'template_start_time' => '20:00',
            'template_end_time' => '07:30',
        ]);
    }

    public function test_weekly_registration_opens_at_twenty_hours_and_closes_at_seven_thirty_next_day(): void
    {
        [$user, $staff] = $this->nochero();
        Sanctum::actingAs($user);
        $this->weeklyTemplate($staff, ['Monday']);

        Carbon::setTestNow('2026-09-07 19:59:00');
        $this->getJson('/api/security/shifts?templates_only=1')
            ->assertOk()
            ->assertJsonPath('data.0.registration.open', false);

        Carbon::setTestNow('2026-09-07 20:00:00');
        $this->getJson('/api/security/shifts?templates_only=1')
            ->assertOk()
            ->assertJsonPath('data.0.registration.open', true)
            ->assertJsonPath('data.0.registration.starts_at', '2026-09-07 20:00')
            ->assertJsonPath('data.0.registration.ends_at', '2026-09-08 07:30');

        Carbon::setTestNow('2026-09-08 07:30:00');
        $this->getJson('/api/security/shifts?templates_only=1')
            ->assertOk()
            ->assertJsonPath('data.0.registration.open', true);

        Carbon::setTestNow('2026-09-08 07:31:00');
        $this->getJson('/api/security/shifts?templates_only=1')
            ->assertOk()
            ->assertJsonPath('data.0.registration.open', false);
    }

    public function test_nochero_can_register_directly_during_an_assigned_overnight_shift(): void
    {
        Carbon::setTestNow('2026-09-08 02:00:00');
        [$user, $staff] = $this->nochero();
        Sanctum::actingAs($user);

        $template = $this->weeklyTemplate($staff, ['Monday']);

        $this->getJson('/api/security/shifts?templates_only=1')
            ->assertOk()
            ->assertJsonPath('data.0.registration.open', true)
            ->assertJsonPath('data.0.registration.can_register', true)
            ->assertJsonPath('data.0.registration.starts_at', '2026-09-07 20:00')
            ->assertJsonPath('data.0.registration.ends_at', '2026-09-08 07:30');

        $this->postJson("/api/security/shifts/{$template->id}/rounds", [
            'payload' => json_encode([
                'recorded_at' => '2030-01-01 10:00:00',
                'overall_status' => 'sin_novedad',
                'observations' => 'Recorrido completado.',
                'sectors' => [[
                    'temp_key' => 'patio',
                    'sector_name' => 'Patio central',
                    'sector_state' => 'sin_novedad',
                ]],
                'incidents' => [],
            ], JSON_THROW_ON_ERROR),
        ])->assertCreated()
            ->assertJsonPath('data.recorded_at', '2026-09-08 02:00')
            ->assertJsonPath('data.round_number', 1);

        $generated = SecurityShift::query()
            ->where('parent_shift_id', $template->id)
            ->whereDate('generated_for_date', '2026-09-07')
            ->firstOrFail();
        $this->assertSame(SecurityShift::STATUS_EN_CURSO, $generated->status);
        $this->assertSame('2026-09-07 20:00', $generated->scheduled_start_at->format('Y-m-d H:i'));
        $this->assertSame('2026-09-08 07:30', $generated->scheduled_end_at->format('Y-m-d H:i'));
        $this->assertDatabaseCount('security_rounds', 1);
    }

    public function test_superadmin_can_add_a_traceable_entry_to_a_previous_scheduled_night(): void
    {
        Carbon::setTestNow('2026-09-08 12:00:00');
        $manager = $this->superAdmin();
        $staff = $this->staff('Nochero Dos');
        Sanctum::actingAs($manager);
        $template = $this->weeklyTemplate($staff, ['Monday']);

        $this->postJson("/api/security/shifts/{$template->id}/rounds", [
            'payload' => json_encode([
                'administrative_entry' => true,
                'occurrence_date' => '2026-09-07',
                'recorded_at' => '2026-09-08 07:15:00',
                'overall_status' => 'observado',
                'observations' => 'Registro incorporado por superadmin.',
                'sectors' => [[
                    'temp_key' => 'acceso',
                    'sector_name' => 'Acceso principal',
                    'sector_state' => 'observado',
                    'observations' => 'Se revisó el cierre.',
                ]],
                'incidents' => [],
            ], JSON_THROW_ON_ERROR),
        ])->assertCreated()
            ->assertJsonPath('message', 'Registro administrativo agregado al turno correctamente.')
            ->assertJsonPath('data.recorded_at', '2026-09-08 07:15')
            ->assertJsonPath('data.recorded_by.id', $manager->id);

        $generated = SecurityShift::query()
            ->where('parent_shift_id', $template->id)
            ->whereDate('generated_for_date', '2026-09-07')
            ->firstOrFail();

        $this->assertSame(SecurityShift::STATUS_FINALIZADO, $generated->status);
        $this->assertSame('2026-09-07 20:00', $generated->scheduled_start_at->format('Y-m-d H:i'));
        $this->assertSame('2026-09-08 07:30', $generated->scheduled_end_at->format('Y-m-d H:i'));
        $this->assertDatabaseHas('security_rounds', [
            'security_shift_id' => $generated->id,
            'recorded_by_user_id' => $manager->id,
            'observations' => 'Registro incorporado por superadmin.',
        ]);
    }

    public function test_nochero_cannot_use_the_superadmin_administrative_override(): void
    {
        Carbon::setTestNow('2026-09-08 12:00:00');
        [$user, $staff] = $this->nochero();
        Sanctum::actingAs($user);
        $template = $this->weeklyTemplate($staff, ['Monday']);

        $this->postJson("/api/security/shifts/{$template->id}/rounds", [
            'payload' => json_encode([
                'administrative_entry' => true,
                'occurrence_date' => '2026-09-07',
                'recorded_at' => '2026-09-08 07:15:00',
                'sectors' => [['sector_name' => 'Acceso principal', 'sector_state' => 'sin_novedad']],
                'incidents' => [],
            ], JSON_THROW_ON_ERROR),
        ])->assertForbidden();

        $this->assertDatabaseCount('security_rounds', 0);
        $this->assertDatabaseMissing('security_shifts', ['parent_shift_id' => $template->id]);
    }

    public function test_superadmin_cannot_attach_an_entry_to_a_day_not_assigned_to_the_nochero(): void
    {
        Carbon::setTestNow('2026-09-09 12:00:00');
        $manager = $this->superAdmin();
        $staff = $this->staff('Nochero Tres');
        Sanctum::actingAs($manager);
        $template = $this->weeklyTemplate($staff, ['Monday']);

        $this->postJson("/api/security/shifts/{$template->id}/rounds", [
            'payload' => json_encode([
                'administrative_entry' => true,
                'occurrence_date' => '2026-09-08',
                'recorded_at' => '2026-09-08 21:00:00',
                'sectors' => [['sector_name' => 'Acceso principal', 'sector_state' => 'sin_novedad']],
                'incidents' => [],
            ], JSON_THROW_ON_ERROR),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('occurrence_date');

        $this->assertDatabaseCount('security_rounds', 0);
        $this->assertDatabaseMissing('security_shifts', ['parent_shift_id' => $template->id]);
    }

    public function test_superadmin_administrative_entry_must_stay_inside_the_night_window(): void
    {
        Carbon::setTestNow('2026-09-09 12:00:00');
        $manager = $this->superAdmin();
        $staff = $this->staff('Nochero Cuatro');
        Sanctum::actingAs($manager);
        $template = $this->weeklyTemplate($staff, ['Monday']);

        $this->postJson("/api/security/shifts/{$template->id}/rounds", [
            'payload' => json_encode([
                'administrative_entry' => true,
                'occurrence_date' => '2026-09-07',
                'recorded_at' => '2026-09-08 08:00:00',
                'sectors' => [['sector_name' => 'Acceso principal', 'sector_state' => 'sin_novedad']],
                'incidents' => [],
            ], JSON_THROW_ON_ERROR),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('recorded_at');

        $this->assertDatabaseCount('security_rounds', 0);
        $this->assertDatabaseMissing('security_shifts', ['parent_shift_id' => $template->id]);
    }

    public function test_round_registration_is_rejected_outside_the_assigned_window(): void
    {
        Carbon::setTestNow('2026-09-08 20:00:00');
        [$user, $staff] = $this->nochero();
        Sanctum::actingAs($user);
        $template = $this->weeklyTemplate($staff, ['Monday']);

        $this->postJson("/api/security/shifts/{$template->id}/rounds", [
            'payload' => json_encode([
                'overall_status' => 'sin_novedad',
                'sectors' => [['sector_name' => 'Patio central', 'sector_state' => 'sin_novedad']],
                'incidents' => [],
            ], JSON_THROW_ON_ERROR),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('schedule');

        $this->assertDatabaseCount('security_rounds', 0);
        $this->assertDatabaseMissing('security_shifts', ['parent_shift_id' => $template->id]);
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

    /**
     * @return array{0: User, 1: Staff}
     */
    private function nochero(): array
    {
        $staff = $this->staff('José Campos');
        $user = User::factory()->create([
            'active' => true,
            'staff_id' => $staff->id,
        ]);
        $role = Role::query()->firstOrCreate(
            ['slug' => 'nochero'],
            ['name' => 'Nochero', 'active' => true],
        );
        $permissions = collect(['ver_rondas_seguridad', 'registrar_rondas_seguridad'])
            ->map(fn (string $slug) => Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'active' => true],
            ));
        $role->permissions()->sync($permissions->pluck('id'));
        $user->roles()->attach($role);

        return [$user, $staff];
    }

    private function staff(string $name): Staff
    {
        return Staff::query()->create([
            'full_name' => $name,
            'rut' => fake()->unique()->numerify('17#######'),
            'status' => 'activo',
            'active' => true,
        ]);
    }

    private function weeklyTemplate(Staff $staff, array $weekdays): SecurityShift
    {
        return SecurityShift::query()->create([
            'staff_id' => $staff->id,
            'schedule_type' => SecurityShift::SCHEDULE_WEEKLY,
            'scheduled_start_at' => '2026-09-01 22:00:00',
            'scheduled_end_at' => '2026-09-02 07:00:00',
            'weekdays' => $weekdays,
            'template_start_time' => '22:00',
            'template_end_time' => '07:00',
            'recurrence_starts_on' => '2026-09-01',
            'status' => SecurityShift::STATUS_PROGRAMADO,
            'coverage_label' => 'Todo el colegio',
        ]);
    }

    private function schedulePayload(Staff $staff, array $weekdays): array
    {
        return [
            'staff_id' => $staff->id,
            'schedule_type' => SecurityShift::SCHEDULE_WEEKLY,
            'weekdays' => $weekdays,
            'recurrence_starts_on' => '2026-09-01',
            'recurrence_ends_on' => null,
            'coverage_label' => 'Todo el colegio',
        ];
    }
}
