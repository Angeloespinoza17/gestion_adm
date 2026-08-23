<?php

namespace App\Services\Attendance\Patterns;

class LateArrivalPatternDetector implements AttendancePatternDetector
{
    public function detect(AttendanceDataset $dataset): ?DetectedPattern
    {
        $recent = $dataset->recent(20);
        $previous = $dataset->records->slice(max(0, $dataset->records->count() - 40), 20);
        $recentCount = $recent->where('minutes_late', '>', 0)->count();
        $previousCount = $previous->where('minutes_late', '>', 0)->count();
        if ($recent->count() < 10 || $recentCount < 4 || $recentCount <= $previousCount) {
            return null;
        }

        return new DetectedPattern(
            'increasing_lateness',
            $recentCount >= 7 ? 'high' : 'medium',
            min(0.94, 0.55 + ($recentCount * 0.05)),
            $recentCount,
            'Los atrasos aumentaron en la ventana lectiva reciente respecto de la ventana anterior.',
            ['recent_late_arrivals' => $recentCount, 'previous_late_arrivals' => $previousCount],
            $dataset->period(),
        );
    }
}
