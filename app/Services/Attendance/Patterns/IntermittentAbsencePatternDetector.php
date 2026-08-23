<?php

namespace App\Services\Attendance\Patterns;

use Carbon\CarbonImmutable;

class IntermittentAbsencePatternDetector implements AttendancePatternDetector
{
    public function detect(AttendanceDataset $dataset): ?DetectedPattern
    {
        $weeks = $dataset->records->groupBy(fn (array $record) => CarbonImmutable::parse($record['date'])->format('o-W'));
        $affected = $weeks->filter(function ($records): bool {
            $absences = $records->where('status', 'absent')->count();

            return $absences >= 1 && $absences <= 2;
        });
        if ($weeks->count() < 5 || $affected->count() < 4 || ($affected->count() / $weeks->count()) < 0.5) {
            return null;
        }

        return new DetectedPattern(
            'intermittent_absence',
            'medium',
            min(0.95, 0.5 + (($affected->count() / $weeks->count()) * 0.45)),
            $affected->count(),
            'Se observan ausencias breves y recurrentes en una proporción relevante de las semanas analizadas.',
            ['weeks_observed' => $weeks->count(), 'affected_weeks' => $affected->count(), 'affected_rate' => round(($affected->count() / $weeks->count()) * 100, 2)],
            $dataset->period(),
        );
    }
}
