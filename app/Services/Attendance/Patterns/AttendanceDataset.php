<?php

namespace App\Services\Attendance\Patterns;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AttendanceDataset
{
    /** @param Collection<int,object|array<string,mixed>> $records */
    public function __construct(
        public readonly int $studentProfileId,
        public readonly int $academicYearId,
        public readonly ?int $courseSectionId,
        Collection $records,
        public readonly Collection $nonSchoolDates,
        public readonly array $settings,
    ) {
        $this->records = $records->map(function ($record): array {
            $row = (array) $record;
            $row['date'] = (string) ($row['attendance_date'] ?? $row['date']);
            $row['status'] = (string) ($row['status'] ?? 'unknown');
            $row['is_justified'] = (bool) ($row['is_justified'] ?? false);
            $row['minutes_late'] = (int) ($row['minutes_late'] ?? 0);
            $row['early_departure'] = (bool) ($row['early_departure'] ?? false);

            return $row;
        })->sortBy('date')->values();
    }

    /** @var Collection<int,array<string,mixed>> */
    public readonly Collection $records;

    public function attendanceRate(?Collection $records = null): ?float
    {
        $records ??= $this->records;
        if ($records->isEmpty()) {
            return null;
        }

        return round(($records->where('status', 'present')->count() / $records->count()) * 100, 2);
    }

    public function recent(int $schoolDays): Collection
    {
        return $this->records->take(-$schoolDays)->values();
    }

    public function currentAbsenceStreak(): int
    {
        $streak = 0;
        foreach ($this->records->reverse() as $record) {
            if ($record['status'] !== 'absent') {
                break;
            }
            $streak++;
        }

        return $streak;
    }

    public function maximumAbsenceStreak(): int
    {
        $maximum = 0;
        $current = 0;
        foreach ($this->records as $record) {
            $current = $record['status'] === 'absent' ? $current + 1 : 0;
            $maximum = max($maximum, $current);
        }

        return $maximum;
    }

    public function comparison(): array
    {
        $window = (int) ($this->settings['comparison_window_school_days'] ?? 20);
        $recent = $this->records->take(-$window)->values();
        $previous = $this->records->slice(max(0, $this->records->count() - ($window * 2)), $window)->values();
        $recentRate = $this->attendanceRate($recent);
        $previousRate = $this->attendanceRate($previous);

        return [
            'recent' => $recentRate,
            'previous' => $previousRate,
            'change' => $recentRate !== null && $previousRate !== null ? round($recentRate - $previousRate, 2) : null,
            'recent_observations' => $recent->count(),
            'previous_observations' => $previous->count(),
        ];
    }

    public function weekday(int $isoWeekday): array
    {
        $target = $this->records->filter(fn (array $record) => CarbonImmutable::parse($record['date'])->isoWeekday() === $isoWeekday);
        $others = $this->records->reject(fn (array $record) => CarbonImmutable::parse($record['date'])->isoWeekday() === $isoWeekday);

        return [
            'observations' => $target->count(),
            'absences' => $target->where('status', 'absent')->count(),
            'absence_rate' => $this->absenceRate($target),
            'other_absence_rate' => $this->absenceRate($others),
        ];
    }

    public function absenceRate(Collection $records): ?float
    {
        return $records->isEmpty() ? null : round(($records->where('status', 'absent')->count() / $records->count()) * 100, 2);
    }

    public function period(): array
    {
        return ['from' => $this->records->first()['date'] ?? null, 'to' => $this->records->last()['date'] ?? null];
    }
}
