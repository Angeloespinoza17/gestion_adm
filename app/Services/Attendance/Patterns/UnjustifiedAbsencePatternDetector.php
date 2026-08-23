<?php

namespace App\Services\Attendance\Patterns;

class UnjustifiedAbsencePatternDetector implements AttendancePatternDetector
{
    public function detect(AttendanceDataset $dataset): ?DetectedPattern
    {
        $absences = $dataset->records->where('status', 'absent');
        $unjustified = $absences->where('is_justified', false)->count();
        $rate = $absences->isEmpty() ? 0 : round(($unjustified / $absences->count()) * 100, 2);
        if ($absences->count() < 4 || $unjustified < 3 || $rate < 60) {
            return null;
        }

        return new DetectedPattern(
            'unjustified_absence',
            $rate >= 80 ? 'high' : 'medium',
            min(0.96, 0.55 + ($absences->count() * 0.04)),
            $unjustified,
            "El {$rate}% de las ausencias del periodo no registra justificación.",
            ['total_absences' => $absences->count(), 'unjustified_absences' => $unjustified, 'unjustified_rate' => $rate],
            $dataset->period(),
        );
    }
}
