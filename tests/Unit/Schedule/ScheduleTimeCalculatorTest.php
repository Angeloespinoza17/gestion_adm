<?php

namespace Tests\Unit\Schedule;

use App\Services\Schedule\ScheduleTimeCalculator;
use PHPUnit\Framework\TestCase;

class ScheduleTimeCalculatorTest extends TestCase
{
    public function test_it_converts_pedagogical_hours_and_minutes(): void
    {
        $calculator = new ScheduleTimeCalculator();

        $this->assertSame(90, $calculator->pedagogicalHoursToMinutes(2, 45));
        $this->assertSame(2.0, $calculator->minutesToPedagogicalHours(90, 45));
    }

    public function test_it_calculates_editable_65_35_distribution(): void
    {
        $calculator = new ScheduleTimeCalculator();

        $distribution = $calculator->calculateDistribution(44, 65, 35, 'none');

        $this->assertSame(28.6, $distribution['lective']);
        $this->assertSame(15.4, $distribution['non_lective']);
    }

    public function test_it_detects_time_overlaps(): void
    {
        $calculator = new ScheduleTimeCalculator();

        $this->assertTrue($calculator->overlaps('08:00', '09:30', '09:00', '10:00'));
        $this->assertFalse($calculator->overlaps('08:00', '09:30', '09:30', '10:15'));
    }
}
