<?php

namespace App\Services\Attendance\Patterns;

use Carbon\CarbonImmutable;

class HolidayAdjacentAbsencePatternDetector implements AttendancePatternDetector
{
    public function __construct(private readonly string $direction) {}

    public function detect(AttendanceDataset $dataset): ?DetectedPattern
    {
        $holidayDates = $dataset->nonSchoolDates->map(fn ($date) => CarbonImmutable::parse($date));
        $matches = $dataset->records->filter(function (array $record) use ($holidayDates): bool {
            if ($record['status'] !== 'absent') {
                return false;
            }
            $date = CarbonImmutable::parse($record['date']);

            return $holidayDates->contains(function (CarbonImmutable $holiday) use ($date): bool {
                $candidate = $this->direction === 'after' ? $holiday->nextWeekday() : $holiday->previousWeekday();

                return $candidate->isSameDay($date);
            });
        });
        if ($matches->count() < 3) {
            return null;
        }
        $label = $this->direction === 'after' ? 'posteriores' : 'previas';

        return new DetectedPattern(
            $this->direction === 'after' ? 'post_holiday_absence' : 'pre_holiday_absence',
            'medium',
            min(0.92, 0.5 + ($matches->count() * 0.09)),
            $matches->count(),
            "Se observan {$matches->count()} ausencias {$label} a interrupciones del calendario escolar.",
            ['occurrences' => $matches->count(), 'dates' => $matches->pluck('date')->values()->all()],
            $dataset->period(),
        );
    }
}
