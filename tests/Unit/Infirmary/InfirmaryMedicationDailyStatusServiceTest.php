<?php

namespace Tests\Unit\Infirmary;

use App\Models\Infirmary\InfirmaryMedicationAdministration;
use App\Models\Infirmary\InfirmaryMedicationAuthorization;
use App\Models\Infirmary\InfirmaryMedicationSchedule;
use App\Services\Infirmary\InfirmaryMedicationDailyStatusService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InfirmaryMedicationDailyStatusServiceTest extends TestCase
{
    private InfirmaryMedicationDailyStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(InfirmaryMedicationDailyStatusService::class);
    }

    #[Test]
    public function it_marks_a_weekday_routine_as_partial_when_one_of_two_doses_is_registered(): void
    {
        $authorization = $this->authorizationWithSchedules(2);
        $authorization->setRelation('administrations', collect([
            $this->administration(101, 1, '2026-07-16 08:05:00', 'administrada'),
        ]));

        $status = $this->service->forAuthorization(
            $authorization,
            Carbon::parse('2026-07-16 12:00:00', 'America/Santiago'),
        );

        $this->assertTrue($status['applicable']);
        $this->assertSame('partial', $status['state']);
        $this->assertSame(2, $status['expected_count']);
        $this->assertSame(1, $status['registered_count']);
        $this->assertSame(1, $status['pending_count']);
        $this->assertSame(2, $status['next_pending_schedule_id']);
    }

    #[Test]
    public function it_excludes_weekends_from_daily_compliance(): void
    {
        $authorization = $this->authorizationWithSchedules(2);

        $status = $this->service->forAuthorization(
            $authorization,
            Carbon::parse('2026-07-18 10:00:00', 'America/Santiago'),
        );

        $this->assertFalse($status['applicable']);
        $this->assertSame('weekend', $status['state']);
        $this->assertSame(0, $status['expected_count']);
        $this->assertSame(0, $status['pending_count']);
    }

    #[Test]
    public function it_reports_an_incident_when_all_slots_are_closed_but_one_was_not_administered(): void
    {
        $authorization = $this->authorizationWithSchedules(2);
        $authorization->setRelation('administrations', collect([
            $this->administration(201, 1, '2026-07-16 08:00:00', 'administrada'),
            $this->administration(202, 2, '2026-07-16 12:30:00', 'no_administrada'),
        ]));

        $status = $this->service->forAuthorization(
            $authorization,
            Carbon::parse('2026-07-16 14:00:00', 'America/Santiago'),
        );

        $this->assertSame('exception', $status['state']);
        $this->assertSame(0, $status['pending_count']);
        $this->assertSame(1, $status['administered_count']);
        $this->assertSame(1, $status['not_administered_count']);
    }

    #[Test]
    public function it_does_not_create_daily_pending_doses_for_sos_routines(): void
    {
        $authorization = $this->authorizationWithSchedules(1);
        $authorization->regimen_type = InfirmaryMedicationAuthorization::REGIMEN_SOS;

        $status = $this->service->forAuthorization(
            $authorization,
            Carbon::parse('2026-07-16 10:00:00', 'America/Santiago'),
        );

        $this->assertFalse($status['applicable']);
        $this->assertSame('sos', $status['state']);
    }

    private function authorizationWithSchedules(int $count): InfirmaryMedicationAuthorization
    {
        $authorization = new InfirmaryMedicationAuthorization([
            'status' => InfirmaryMedicationAuthorization::STATUS_VIGENTE,
            'regimen_type' => InfirmaryMedicationAuthorization::REGIMEN_PERMANENTE,
            'start_date' => '2026-01-01',
            'end_date' => null,
            'daily_dose_count' => $count,
            'schedule_mode' => InfirmaryMedicationAuthorization::SCHEDULE_FIXED_TIME,
        ]);

        $schedules = new Collection;
        foreach (range(1, $count) as $doseOrder) {
            $schedule = new InfirmaryMedicationSchedule([
                'dose_order' => $doseOrder,
                'scheduled_time' => $doseOrder === 1 ? '08:00:00' : '12:30:00',
                'active' => true,
            ]);
            $schedule->id = $doseOrder;
            $schedules->push($schedule);
        }

        $authorization->setRelation('schedules', $schedules);
        $authorization->setRelation('administrations', collect());

        return $authorization;
    }

    private function administration(int $id, int $scheduleId, string $administeredAt, string $status): InfirmaryMedicationAdministration
    {
        $administration = new InfirmaryMedicationAdministration([
            'schedule_id' => $scheduleId,
            'administered_at' => $administeredAt,
            'scheduled_for_date' => substr($administeredAt, 0, 10),
            'administration_status' => $status,
        ]);
        $administration->id = $id;

        return $administration;
    }
}
