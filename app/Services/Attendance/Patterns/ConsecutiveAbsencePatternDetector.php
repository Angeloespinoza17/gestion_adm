<?php

namespace App\Services\Attendance\Patterns;

class ConsecutiveAbsencePatternDetector implements AttendancePatternDetector
{
    public function detect(AttendanceDataset $dataset): ?DetectedPattern
    {
        $streak = $dataset->currentAbsenceStreak();
        if ($streak < 3) {
            return null;
        }

        return new DetectedPattern(
            'consecutive_absence',
            $streak >= 5 ? 'critical' : 'high',
            min(0.99, 0.7 + ($streak * 0.05)),
            $streak,
            "Acumula {$streak} días lectivos consecutivos con ausencia registrada.",
            ['current_streak' => $streak, 'maximum_streak' => $dataset->maximumAbsenceStreak()],
            $dataset->period(),
        );
    }
}
