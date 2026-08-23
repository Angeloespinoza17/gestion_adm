<?php

namespace App\Services\Attendance\Patterns;

class AttendanceChangePatternDetector implements AttendancePatternDetector
{
    public function detect(AttendanceDataset $dataset): ?DetectedPattern
    {
        $comparison = $dataset->comparison();
        $minimum = (float) ($dataset->settings['meaningful_change_points'] ?? 5);
        if ($comparison['change'] === null || min($comparison['recent_observations'], $comparison['previous_observations']) < 8 || abs($comparison['change']) < $minimum) {
            return null;
        }
        $improving = $comparison['change'] > 0;
        $points = abs((float) $comparison['change']);

        return new DetectedPattern(
            $improving ? 'attendance_recovery' : 'recent_attendance_drop',
            $improving ? 'positive' : ($points >= 10 ? 'high' : 'medium'),
            min(0.97, 0.62 + ($points / 50)),
            (int) $comparison['recent_observations'],
            $improving
                ? "La asistencia mejoró {$points} puntos porcentuales respecto del periodo lectivo comparable anterior."
                : "La asistencia disminuyó {$points} puntos porcentuales respecto del periodo lectivo comparable anterior.",
            $comparison,
            $dataset->period(),
        );
    }
}
