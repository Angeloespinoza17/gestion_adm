<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceProjectionSetting;
use App\Models\Attendance\AttendanceRecord;
use App\Models\StudentEnrollment;

class AttendanceProjectionService
{
    public function settings(int $academicYearId): AttendanceProjectionSetting
    {
        return AttendanceProjectionSetting::query()->firstOrCreate(
            ['academic_year_id' => $academicYearId],
            [
                'monthly_unit_value' => config('attendance.projection.monthly_unit_value', 0),
                'attendance_factor' => config('attendance.projection.attendance_factor', 1),
                'target_attendance_rate' => config('attendance.projection.target_attendance_rate', 85),
                'conservative_delta' => config('attendance.projection.conservative_delta', 5),
                'custom_attendance_rate' => config('attendance.projection.custom_attendance_rate', 90),
                'additional_adjustments' => config('attendance.projection.additional_adjustments', 0),
                'annual_school_days' => config('attendance.projection.annual_school_days', 190),
                'calculation_window' => config('attendance.projection.calculation_window', 'current_month'),
                'currency' => config('attendance.projection.currency', 'CLP'),
            ],
        );
    }

    public function build(
        int $academicYearId,
        ?int $courseSectionId,
        float $attendanceRate,
        int $schoolDays,
        ?int $presentStudentDays = null,
        ?int $expectedStudentDays = null,
    ): array {
        $settings = $this->settings($academicYearId);
        $roster = StudentEnrollment::query()
            ->where('academic_year_id', $academicYearId)
            ->when($courseSectionId, fn ($query) => $query->where('course_section_id', $courseSectionId))
            ->whereNotIn('enrollment_status', StudentEnrollment::NON_ROSTER_STATUS_VALUES)
            ->count();
        $remainingDays = max(0, $settings->annual_school_days - $schoolDays);
        $observedExpected = $expectedStudentDays ?? ($roster * $schoolDays);
        $observedPresent = $presentStudentDays ?? (int) round($observedExpected * ($attendanceRate / 100));
        $remainingExpected = $roster * $remainingDays;
        $projectedExpected = $observedExpected + $remainingExpected;
        $totalProjectedDays = $schoolDays + $remainingDays;
        $currentAverageAttendance = $schoolDays > 0 ? $observedPresent / $schoolDays : 0;
        $currentRevenue = $this->revenue($currentAverageAttendance, $settings);
        $scenarios = collect([
            ['key' => 'trend', 'label' => 'Tendencia actual', 'scenario_rate' => $attendanceRate],
            ['key' => 'conservative', 'label' => 'Conservador', 'scenario_rate' => $attendanceRate - (float) $settings->conservative_delta],
            ['key' => 'target', 'label' => 'Meta institucional', 'scenario_rate' => (float) $settings->target_attendance_rate],
            ['key' => 'custom', 'label' => 'Personalizado', 'scenario_rate' => (float) $settings->custom_attendance_rate],
        ])->map(function (array $scenario) use ($observedPresent, $remainingExpected, $projectedExpected, $totalProjectedDays, $settings, $remainingDays, $currentRevenue) {
            $scenarioRate = max(0, min(100, $scenario['scenario_rate']));
            $projectedPresent = $observedPresent + ($remainingExpected * ($scenarioRate / 100));
            $projectedRate = $projectedExpected > 0 ? ($projectedPresent / $projectedExpected) * 100 : 0;
            $averageAttendance = $totalProjectedDays > 0 ? $projectedPresent / $totalProjectedDays : 0;
            $projectedRevenue = $this->revenue($averageAttendance, $settings);

            return [
                ...$scenario,
                'scenario_rate' => round($scenarioRate, 2),
                'attendance_rate' => round($projectedRate, 2),
                'average_daily_attendance' => round($averageAttendance, 2),
                'remaining_school_days' => $remainingDays,
                'projected_present_student_days' => round($projectedPresent, 2),
                'projected_expected_student_days' => $projectedExpected,
                'monthly_revenue' => $projectedRevenue,
                'revenue_difference' => round($projectedRevenue - $currentRevenue, 2),
                'revenue_difference_rate' => $currentRevenue != 0.0 ? round((($projectedRevenue - $currentRevenue) / abs($currentRevenue)) * 100, 2) : null,
            ];
        })->all();

        $monthly = AttendanceRecord::query()
            ->where('academic_year_id', $academicYearId)
            ->when($courseSectionId, fn ($query) => $query->where('course_section_id', $courseSectionId))
            ->selectRaw('substr(attendance_date, 1, 7) as period')
            ->selectRaw('COUNT(DISTINCT attendance_date) as school_days')
            ->selectRaw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(function ($row) use ($settings, $roster) {
                $average = $row->school_days > 0 ? (float) $row->present / (int) $row->school_days : 0;
                $revenue = $this->revenue($average, $settings);
                $potential = $this->revenue($roster, $settings);

                return [
                    'period' => $row->period,
                    'average_daily_attendance' => round($average, 2),
                    'actual_revenue' => round($revenue, 2),
                    'potential_revenue' => round($potential, 2),
                    'efficiency_rate' => $potential > 0 ? round(($revenue / $potential) * 100, 2) : 0,
                ];
            });

        return [
            'settings' => [
                'id' => $settings->id,
                'monthly_unit_value' => (float) $settings->monthly_unit_value,
                'attendance_factor' => (float) $settings->attendance_factor,
                'target_attendance_rate' => (float) $settings->target_attendance_rate,
                'conservative_delta' => (float) $settings->conservative_delta,
                'custom_attendance_rate' => (float) $settings->custom_attendance_rate,
                'additional_adjustments' => (float) $settings->additional_adjustments,
                'annual_school_days' => $settings->annual_school_days,
                'calculation_window' => $settings->calculation_window,
                'valid_from' => $settings->valid_from?->format('Y-m-d'),
                'valid_to' => $settings->valid_to?->format('Y-m-d'),
                'configuration_source' => $settings->configuration_source,
                'currency' => $settings->currency,
            ],
            'configuration_required' => (float) $settings->monthly_unit_value <= 0 || (float) $settings->attendance_factor <= 0,
            'is_estimate' => true,
            'roster_students' => $roster,
            'remaining_school_days' => $remainingDays,
            'current_average_daily_attendance' => round($currentAverageAttendance, 2),
            'current_estimated_revenue' => $currentRevenue,
            'scenarios' => $scenarios,
            'monthly' => $monthly,
            'accumulated_actual_revenue' => round($monthly->sum('actual_revenue'), 2),
            'accumulated_potential_revenue' => round($monthly->sum('potential_revenue'), 2),
        ];
    }

    private function revenue(float $attendanceBase, AttendanceProjectionSetting $settings): float
    {
        return round(
            ($attendanceBase * (float) $settings->monthly_unit_value * (float) $settings->attendance_factor)
                + (float) $settings->additional_adjustments,
            2,
        );
    }
}
