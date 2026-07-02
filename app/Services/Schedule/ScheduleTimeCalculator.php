<?php

namespace App\Services\Schedule;

class ScheduleTimeCalculator
{
    /**
     * @return array{lective: float, non_lective: float}
     */
    public function calculateDistribution(
        float $weeklyHours,
        float $lectivePercentage,
        float $nonLectivePercentage,
        string $roundingMode = 'nearest',
        float $roundingStep = 0.1,
    ): array {
        $lective = $weeklyHours * ($lectivePercentage / 100);
        $nonLective = $weeklyHours * ($nonLectivePercentage / 100);

        return [
            'lective' => $this->roundHours($lective, $roundingMode, $roundingStep),
            'non_lective' => $this->roundHours($nonLective, $roundingMode, $roundingStep),
        ];
    }

    public function minutesToPedagogicalHours(int $minutes, int $pedagogicalHourMinutes, string $roundingMode = 'none'): float
    {
        if ($pedagogicalHourMinutes <= 0) {
            return 0;
        }

        $hours = $minutes / $pedagogicalHourMinutes;

        return $this->roundHours($hours, $roundingMode, 0.01);
    }

    public function pedagogicalHoursToMinutes(float $hours, int $pedagogicalHourMinutes): int
    {
        return (int) round($hours * $pedagogicalHourMinutes);
    }

    public function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time);

        return ((int) ($parts[0] ?? 0) * 60) + (int) ($parts[1] ?? 0);
    }

    public function minutesBetween(string $startTime, string $endTime): int
    {
        return max(0, $this->timeToMinutes($endTime) - $this->timeToMinutes($startTime));
    }

    public function overlaps(string $firstStart, string $firstEnd, string $secondStart, string $secondEnd): bool
    {
        return $this->timeToMinutes($firstStart) < $this->timeToMinutes($secondEnd)
            && $this->timeToMinutes($secondStart) < $this->timeToMinutes($firstEnd);
    }

    public function contains(string $containerStart, string $containerEnd, string $innerStart, string $innerEnd): bool
    {
        return $this->timeToMinutes($containerStart) <= $this->timeToMinutes($innerStart)
            && $this->timeToMinutes($innerEnd) <= $this->timeToMinutes($containerEnd);
    }

    public function roundHours(float $hours, string $roundingMode, float $step = 0.1): float
    {
        if ($roundingMode === 'none' || $step <= 0) {
            return round($hours, 2);
        }

        $factor = $hours / $step;

        $rounded = match ($roundingMode) {
            'up' => ceil($factor) * $step,
            'down' => floor($factor) * $step,
            default => round($factor) * $step,
        };

        return round($rounded, 2);
    }

    public function isLectiveActivity(string $activityType, ?string $layerType = null): bool
    {
        if ($layerType === 'lective') {
            return true;
        }

        return in_array($activityType, ['lective_class', 'class', 'jefatura_course'], true);
    }

    public function isNonLectiveActivity(string $activityType, ?string $layerType = null): bool
    {
        if ($layerType === 'non_lective') {
            return true;
        }

        return in_array($activityType, ['non_lective', 'meeting', 'coordination', 'pie', 'workshop', 'extracurricular'], true);
    }
}
