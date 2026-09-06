<?php

namespace App\Services\Convivencia;

use App\Models\Attendance\SchoolDay;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class ConvivenciaProtocolDeadlineService
{
    /**
     * @return array{due_at: ?Carbon, source: string, fallback_used: bool, fallback_reason: ?string}
     */
    public function calculate(CarbonInterface $anchor, ?int $value, ?string $unit, ?int $academicYearId = null): array
    {
        if (! $value || $value < 1) {
            return [
                'due_at' => null,
                'source' => 'none',
                'fallback_used' => false,
                'fallback_reason' => null,
            ];
        }

        $anchor = Carbon::instance($anchor)->copy();
        $unit = $unit ?: 'calendar_days';

        if (in_array($unit, ['external', 'external_defined'], true)) {
            return [
                'due_at' => null,
                'source' => 'external_deadline',
                'fallback_used' => false,
                'fallback_reason' => null,
            ];
        }

        if ($unit === 'hours') {
            return $this->result($anchor->addHours($value), 'elapsed_hours');
        }

        if ($unit === 'calendar_days') {
            return $this->result($anchor->addDays($value), 'calendar_days');
        }

        if ($unit === 'school_days' && $academicYearId) {
            $dates = SchoolDay::query()
                ->where('academic_year_id', $academicYearId)
                ->where('is_school_day', true)
                ->where('status', 'confirmed')
                ->whereDate('date', '>', $anchor->toDateString())
                ->orderBy('date')
                ->limit($value)
                ->pluck('date');

            if ($dates->count() === $value) {
                $dueAt = Carbon::parse($dates->last(), $anchor->getTimezone())
                    ->setTime($anchor->hour, $anchor->minute, $anchor->second);

                return $this->result($dueAt, 'confirmed_school_days');
            }

            return $this->result(
                $this->addBusinessDays($anchor, $value),
                'business_days_fallback',
                true,
                'No hay suficientes días lectivos confirmados para calcular el plazo completo.'
            );
        }

        if ($unit === 'school_days') {
            return $this->result(
                $this->addBusinessDays($anchor, $value),
                'business_days_fallback',
                true,
                'La activación no tiene un año académico asociado.'
            );
        }

        return $this->result($this->addBusinessDays($anchor, $value), 'business_days');
    }

    private function addBusinessDays(Carbon $anchor, int $days): Carbon
    {
        $candidate = $anchor->copy();
        $remaining = $days;

        while ($remaining > 0) {
            $candidate->addDay();
            if (! $candidate->isWeekend()) {
                $remaining--;
            }
        }

        return $candidate;
    }

    /**
     * @return array{due_at: Carbon, source: string, fallback_used: bool, fallback_reason: ?string}
     */
    private function result(Carbon $dueAt, string $source, bool $fallbackUsed = false, ?string $fallbackReason = null): array
    {
        return [
            'due_at' => $dueAt,
            'source' => $source,
            'fallback_used' => $fallbackUsed,
            'fallback_reason' => $fallbackReason,
        ];
    }
}
