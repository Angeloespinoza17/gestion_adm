<?php

namespace App\Services\Attendance\Patterns;

class WeekdayAbsencePatternDetector implements AttendancePatternDetector
{
    public function __construct(private readonly int $isoWeekday, private readonly string $type, private readonly string $label) {}

    public function detect(AttendanceDataset $dataset): ?DetectedPattern
    {
        $metrics = $dataset->weekday($this->isoWeekday);
        $minimum = (int) ($dataset->settings['minimum_weekday_observations'] ?? 5);
        $occurrences = (int) $metrics['absences'];
        $baseline = max(1.0, (float) ($metrics['other_absence_rate'] ?? 0));
        $multiplier = (float) ($dataset->settings['weekday_rate_multiplier'] ?? 1.75);
        if ($metrics['observations'] < $minimum || $occurrences < 3 || (float) $metrics['absence_rate'] < $baseline * $multiplier) {
            return null;
        }
        $confidence = min(0.98, 0.45 + ($occurrences * 0.07) + (min(30, $metrics['observations']) / 100));
        $severity = $metrics['absence_rate'] >= 40 ? 'high' : 'medium';

        return new DetectedPattern(
            $this->type,
            $severity,
            $confidence,
            $occurrences,
            "La frecuencia de ausencias los {$this->label} es superior a la observada en los otros días lectivos.",
            $metrics,
            $dataset->period(),
        );
    }
}
