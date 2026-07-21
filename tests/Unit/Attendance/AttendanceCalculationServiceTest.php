<?php

namespace Tests\Unit\Attendance;

use App\Services\Attendance\AttendanceCalculationService;
use PHPUnit\Framework\TestCase;

class AttendanceCalculationServiceTest extends TestCase
{
    private AttendanceCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AttendanceCalculationService;
    }

    public function test_rates_gaps_and_variations_are_safe_with_empty_denominators(): void
    {
        $this->assertSame(85.0, $this->service->rate(85, 100));
        $this->assertNull($this->service->rate(0, 0));
        $this->assertSame(-5.0, $this->service->gap(85, 90));
        $this->assertSame(['absolute' => 5.0, 'percentage' => 5.88], $this->service->variation(90, 85));
        $this->assertSame(['absolute' => 90.0, 'percentage' => null], $this->service->variation(90, 0));
    }

    public function test_descriptive_statistics_and_trend_are_reproducible(): void
    {
        $statistics = $this->service->descriptive([80, 90, 90, 100]);
        $this->assertSame(90.0, $statistics['average']);
        $this->assertSame(90.0, $statistics['median']);
        $this->assertSame(90.0, $statistics['mode']);
        $this->assertSame(80.0, $statistics['minimum']);
        $this->assertSame(100.0, $statistics['maximum']);
        $this->assertSame(20.0, $statistics['range']);
        $this->assertSame('declining', $this->service->trend([95, 93, 90, 86])['direction']);
        $this->assertSame('improving', $this->service->trend([80, 84, 88, 92])['direction']);
        $this->assertSame('insufficient_data', $this->service->trend([90])['direction']);
    }

    public function test_projection_discloses_reachability_and_required_attendances(): void
    {
        $projection = $this->service->project(80, 100, 20, 100, 90);
        $this->assertSame(83.33, $projection['projected_rate']);
        $this->assertSame(28, $projection['required_future_attendances']);
        $this->assertFalse($projection['target_is_mathematically_reachable']);
        $this->assertSame(8, $projection['additional_attendances_needed']);
    }

    public function test_consecutive_absences_reset_after_a_presence(): void
    {
        $this->assertSame(3, $this->service->maximumConsecutiveAbsences(['absent', 'absent', 'present', 'absent', 'absent', 'absent']));
    }
}
