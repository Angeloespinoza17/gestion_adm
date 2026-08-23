<?php

namespace Tests\Unit\Attendance;

use App\Services\Attendance\AttendancePatternEngine;
use App\Services\Attendance\AttendanceRiskEngine;
use App\Services\Attendance\Patterns\AttendanceDataset;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class AttendanceRiskAndPatternEngineTest extends TestCase
{
    public function test_risk_engine_is_deterministic_explainable_and_escalates_critical_streaks(): void
    {
        $engine = new AttendanceRiskEngine;
        $settings = require dirname(__DIR__, 3).'/config/attendance_management.php';
        $metrics = [
            'attendance_percentage' => 88.5, 'attendance_last_30_days' => 76, 'attendance_last_15_days' => 60,
            'consecutive_absences' => 5, 'unjustified_absence_rate' => 70, 'late_arrivals' => 3,
            'has_previous_case' => true, 'trend_drop_points' => 10, 'recent_absences' => 5,
        ];

        $first = $engine->calculate($metrics, $settings);
        $second = $engine->calculate($metrics, $settings);

        $this->assertSame($first, $second);
        $this->assertSame('critical', $first['level']);
        $this->assertNotEmpty($first['critical_triggers']);
        $this->assertSame($first['reasons'], $first['explanations']);
        $this->assertGreaterThan(0, $first['score']);
        $this->assertLessThanOrEqual(100, $first['score']);
    }

    public function test_pattern_engine_requires_observations_and_detects_recurrent_weekday_absence(): void
    {
        $records = collect();
        $date = new \DateTimeImmutable('2026-03-02');
        for ($week = 0; $week < 8; $week++) {
            for ($weekday = 0; $weekday < 5; $weekday++) {
                $day = $date->modify('+'.(($week * 7) + $weekday).' days');
                $records->push([
                    'attendance_date' => $day->format('Y-m-d'),
                    'status' => $weekday === 0 ? 'absent' : 'present',
                    'is_justified' => false, 'minutes_late' => 0, 'early_departure' => false,
                ]);
            }
        }
        $dataset = new AttendanceDataset(1, 1, 1, $records, new Collection, [
            'minimum_weekday_observations' => 5, 'weekday_rate_multiplier' => 1.75,
            'minimum_pattern_occurrences' => 3, 'recent_window_school_days' => 20,
            'comparison_window_school_days' => 20, 'meaningful_change_points' => 5,
        ]);

        $patterns = (new AttendancePatternEngine)->detect($dataset);

        $this->assertTrue($patterns->contains(fn (array $pattern) => $pattern['type'] === 'monday_absence'));
        $monday = $patterns->firstWhere('type', 'monday_absence');
        $this->assertGreaterThanOrEqual(5, $monday['occurrences']);
        $this->assertGreaterThanOrEqual(.6, $monday['confidence']);
    }
}
