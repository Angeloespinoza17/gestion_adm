<?php

namespace Tests\Feature\Infirmary;

use App\Models\Infirmary\InfirmaryMedication;
use App\Models\Infirmary\InfirmaryMedicationAdministration;
use App\Models\Infirmary\InfirmaryMedicationAuthorization;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InfirmaryMedicationHistoryTest extends TestCase
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

    public function test_it_reconstructs_the_daily_status_for_the_requested_historical_date(): void
    {
        [$authorization, $schedule] = $this->routine();

        $this->administration($authorization, $schedule->id, '2026-08-17 08:05:00', 'administrada');
        $this->administration($authorization, $schedule->id, '2026-08-18 08:10:00', 'no_administrada');

        $this->getJson('/api/infirmary/medication-authorizations?control_date=2026-08-17')
            ->assertOk()
            ->assertJsonPath('daily_status_date', '2026-08-17')
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(1, 'data.0.administrations')
            ->assertJsonPath('data.0.daily_status.date', '2026-08-17')
            ->assertJsonPath('data.0.daily_status.state', 'completed')
            ->assertJsonPath('data.0.daily_status.registered_count', 1)
            ->assertJsonPath('data.0.administrations.0.administration_status', 'administrada');

        $this->getJson("/api/infirmary/medication-authorizations/{$authorization->id}?control_date=2026-08-18")
            ->assertOk()
            ->assertJsonPath('data.daily_status.date', '2026-08-18')
            ->assertJsonPath('data.daily_status.state', 'exception')
            ->assertJsonPath('data.daily_status.not_administered_count', 1);
    }

    public function test_it_rejects_an_invalid_control_date(): void
    {
        $this->getJson('/api/infirmary/medication-authorizations?control_date=19-08-2026')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('control_date');
    }

    /**
     * @return array{InfirmaryMedicationAuthorization, \App\Models\Infirmary\InfirmaryMedicationSchedule}
     */
    private function routine(): array
    {
        $student = StudentProfile::query()->create([
            'first_name' => 'Camila',
            'last_name' => 'Rojas',
            'rut' => '21.111.111-1',
        ]);
        $medication = InfirmaryMedication::query()->create([
            'inventory_type' => 'medication',
            'source_type' => 'school',
            'name' => 'Paracetamol',
            'commercial_name' => 'Kitadol',
            'unit' => 'comprimido',
            'current_stock' => 20,
            'minimum_stock' => 2,
            'status' => 'disponible',
            'active' => true,
        ]);
        $authorization = InfirmaryMedicationAuthorization::query()->create([
            'student_profile_id' => $student->id,
            'medication_id' => $medication->id,
            'dose' => '500 mg',
            'dose_amount' => 500,
            'dose_unit' => 'mg',
            'administration_route' => 'oral',
            'frequency' => 'Una vez al día',
            'daily_dose_count' => 1,
            'schedule_mode' => 'fixed_time',
            'schedule_text' => '08:00',
            'regimen_type' => 'permanente',
            'start_date' => '2026-01-01',
            'status' => 'vigente',
        ]);
        $schedule = $authorization->schedules()->create([
            'dose_order' => 1,
            'scheduled_time' => '08:00',
            'active' => true,
        ]);

        return [$authorization, $schedule];
    }

    private function administration(
        InfirmaryMedicationAuthorization $authorization,
        int $scheduleId,
        string $administeredAt,
        string $status,
    ): void {
        InfirmaryMedicationAdministration::query()->create([
            'authorization_id' => $authorization->id,
            'schedule_id' => $scheduleId,
            'medication_id' => $authorization->medication_id,
            'student_profile_id' => $authorization->student_profile_id,
            'administered_at' => $administeredAt,
            'scheduled_for_date' => substr($administeredAt, 0, 10),
            'administration_status' => $status,
            'quantity_administered' => $status === 'administrada' ? 1 : 0,
            'dose_amount' => 500,
            'dose_unit' => 'mg',
            'administration_route' => 'oral',
            'non_administration_reason' => $status === 'no_administrada' ? 'estudiante_ausente' : null,
            'source_type' => 'autorizacion',
        ]);
    }
}
