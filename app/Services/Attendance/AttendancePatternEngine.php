<?php

namespace App\Services\Attendance;

use App\Services\Attendance\Patterns\AttendanceChangePatternDetector;
use App\Services\Attendance\Patterns\AttendanceDataset;
use App\Services\Attendance\Patterns\ConsecutiveAbsencePatternDetector;
use App\Services\Attendance\Patterns\EarlyDeparturePatternDetector;
use App\Services\Attendance\Patterns\HolidayAdjacentAbsencePatternDetector;
use App\Services\Attendance\Patterns\IntermittentAbsencePatternDetector;
use App\Services\Attendance\Patterns\LateArrivalPatternDetector;
use App\Services\Attendance\Patterns\UnjustifiedAbsencePatternDetector;
use App\Services\Attendance\Patterns\WeekdayAbsencePatternDetector;
use Illuminate\Support\Collection;

class AttendancePatternEngine
{
    /** @return Collection<int,array<string,mixed>> */
    public function detect(AttendanceDataset $dataset): Collection
    {
        $detectors = [
            new WeekdayAbsencePatternDetector(1, 'monday_absence', 'lunes'),
            new WeekdayAbsencePatternDetector(5, 'friday_absence', 'viernes'),
            new ConsecutiveAbsencePatternDetector,
            new AttendanceChangePatternDetector,
            new IntermittentAbsencePatternDetector,
            new HolidayAdjacentAbsencePatternDetector('after'),
            new HolidayAdjacentAbsencePatternDetector('before'),
            new LateArrivalPatternDetector,
            new EarlyDeparturePatternDetector,
            new UnjustifiedAbsencePatternDetector,
        ];

        return collect($detectors)
            ->map(fn ($detector) => $detector->detect($dataset))
            ->filter()
            ->map(fn ($pattern) => $pattern->toArray())
            ->values();
    }
}
