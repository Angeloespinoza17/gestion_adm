<?php

namespace App\Services\Attendance\Patterns;

class EarlyDeparturePatternDetector implements AttendancePatternDetector
{
    public function detect(AttendanceDataset $dataset): ?DetectedPattern
    {
        $departures = $dataset->records->where('early_departure', true)->count();
        if ($dataset->records->count() < 15 || $departures < 3) {
            return null;
        }

        return new DetectedPattern(
            'recurring_early_departure',
            $departures >= 6 ? 'high' : 'medium',
            min(0.93, 0.5 + ($departures * 0.07)),
            $departures,
            'Se observan retiros anticipados recurrentes durante el periodo analizado.',
            ['early_departures' => $departures, 'observations' => $dataset->records->count()],
            $dataset->period(),
        );
    }
}
