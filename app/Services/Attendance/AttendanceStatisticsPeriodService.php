<?php

namespace App\Services\Attendance;

use App\Models\AcademicYear;
use App\Models\Attendance\SchoolDay;
use Carbon\CarbonImmutable;

class AttendanceStatisticsPeriodService
{
    public function resolve(array $filters): array
    {
        $year = $this->year(isset($filters['academic_year_id']) ? (int) $filters['academic_year_id'] : null);
        $today = CarbonImmutable::today(config('app.timezone'));
        $period = $filters['period'] ?? 'academic_year';
        [$from, $to] = match ($period) {
            'today' => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            'current_week' => [$today->startOfWeek(), $today->endOfWeek()],
            'previous_week' => [$today->subWeek()->startOfWeek(), $today->subWeek()->endOfWeek()],
            'last_7_school_days' => $this->lastSchoolDays($year->id, 7, $today),
            'last_14_school_days' => $this->lastSchoolDays($year->id, 14, $today),
            'last_30_days' => [$today->subDays(29), $today],
            'current_month' => [$today->startOfMonth(), $today->endOfMonth()],
            'previous_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'quarter' => [$today->firstOfQuarter(), $today->lastOfQuarter()],
            'semester' => $today->month <= 6
                ? [$today->startOfYear(), $today->setMonth(6)->endOfMonth()]
                : [$today->setMonth(7)->startOfMonth(), $today->endOfYear()],
            'custom' => [
                CarbonImmutable::parse($filters['from'] ?? $year->starts_at),
                CarbonImmutable::parse($filters['to'] ?? $year->ends_at),
            ],
            default => [CarbonImmutable::parse($year->starts_at), CarbonImmutable::parse($year->ends_at)],
        };

        $yearStart = CarbonImmutable::parse($year->starts_at);
        $yearEnd = CarbonImmutable::parse($year->ends_at);
        $from = $from->max($yearStart);
        $to = $to->min($yearEnd);
        if ($to->lt($from)) {
            $from = $yearStart;
            $to = $yearEnd;
        }
        $length = $from->diffInDays($to) + 1;
        $previousTo = $from->subDay();
        $previousFrom = $previousTo->subDays($length - 1);
        $previousYear = AcademicYear::query()->where('year', $year->year - 1)->first();

        return [
            'academic_year' => $year,
            'period' => $period,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'previous_from' => $previousFrom->toDateString(),
            'previous_to' => $previousTo->toDateString(),
            'comparison_year' => $previousYear,
            'comparison_from' => $previousYear ? $this->safeYearDate($from, $previousYear->year) : null,
            'comparison_to' => $previousYear ? $this->safeYearDate($to, $previousYear->year) : null,
        ];
    }

    private function year(?int $id): AcademicYear
    {
        return AcademicYear::query()
            ->when($id, fn ($query) => $query->whereKey($id))
            ->when(! $id, fn ($query) => $query->where('is_active', true))
            ->first() ?? AcademicYear::query()->orderByDesc('year')->firstOrFail();
    }

    private function lastSchoolDays(int $yearId, int $days, CarbonImmutable $today): array
    {
        $dates = SchoolDay::query()
            ->where('academic_year_id', $yearId)
            ->where('is_school_day', true)
            ->whereDate('date', '<=', $today)
            ->orderByDesc('date')
            ->limit($days)
            ->pluck('date')
            ->map(fn ($date) => CarbonImmutable::parse($date));

        return $dates->isEmpty() ? [$today->subDays($days - 1), $today] : [$dates->min(), $dates->max()];
    }

    private function safeYearDate(CarbonImmutable $date, int $year): string
    {
        $day = min($date->day, CarbonImmutable::create($year, $date->month, 1)->daysInMonth);

        return CarbonImmutable::create($year, $date->month, $day)->toDateString();
    }
}
