<?php

namespace App\Services\Attendance;

use Illuminate\Support\Collection;

class AttendanceCalculationService
{
    public function rate(int|float $part, int|float $total): ?float
    {
        return $total > 0 ? round(($part / $total) * 100, 2) : null;
    }

    public function gap(?float $value, ?float $target): ?float
    {
        return $value !== null && $target !== null ? round($value - $target, 2) : null;
    }

    public function variation(?float $current, ?float $previous): array
    {
        $absolute = $current !== null && $previous !== null ? round($current - $previous, 2) : null;
        $relative = $absolute !== null && $previous != 0.0 ? round(($absolute / abs($previous)) * 100, 2) : null;

        return ['absolute' => $absolute, 'percentage' => $relative];
    }

    public function descriptive(iterable $values): array
    {
        $values = collect($values)->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (float) $value)->sort()->values();
        $count = $values->count();

        if ($count === 0) {
            return [
                'count' => 0, 'average' => null, 'median' => null, 'mode' => null,
                'standard_deviation' => null, 'coefficient_variation' => null,
                'minimum' => null, 'maximum' => null, 'range' => null,
                'percentiles' => ['p10' => null, 'p25' => null, 'p50' => null, 'p75' => null, 'p90' => null],
            ];
        }

        $average = $values->avg();
        $variance = $values->sum(fn (float $value) => ($value - $average) ** 2) / $count;
        $deviation = sqrt($variance);
        $frequencies = $values->countBy()->sortDesc();
        $maximumFrequency = (int) ($frequencies->first() ?? 0);
        $mode = $maximumFrequency > 1 ? (float) $frequencies->filter(fn ($frequency) => $frequency === $maximumFrequency)->keys()->first() : null;

        return [
            'count' => $count,
            'average' => round($average, 2),
            'median' => round((float) $values->median(), 2),
            'mode' => $mode,
            'standard_deviation' => round($deviation, 2),
            'coefficient_variation' => $average != 0.0 ? round(($deviation / abs($average)) * 100, 2) : null,
            'minimum' => round((float) $values->first(), 2),
            'maximum' => round((float) $values->last(), 2),
            'range' => round((float) $values->last() - (float) $values->first(), 2),
            'percentiles' => [
                'p10' => $this->percentile($values, 10),
                'p25' => $this->percentile($values, 25),
                'p50' => $this->percentile($values, 50),
                'p75' => $this->percentile($values, 75),
                'p90' => $this->percentile($values, 90),
            ],
        ];
    }

    public function trend(iterable $values): array
    {
        $values = collect($values)->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (float) $value)->values();
        $count = $values->count();
        if ($count < 2) {
            return ['slope' => null, 'direction' => 'insufficient_data', 'change_speed' => null];
        }

        $meanX = ($count - 1) / 2;
        $meanY = $values->avg();
        $numerator = 0.0;
        $denominator = 0.0;
        foreach ($values as $index => $value) {
            $numerator += ($index - $meanX) * ($value - $meanY);
            $denominator += ($index - $meanX) ** 2;
        }
        $slope = $denominator > 0 ? $numerator / $denominator : 0.0;

        return [
            'slope' => round($slope, 3),
            'direction' => $slope > 0.15 ? 'improving' : ($slope < -0.15 ? 'declining' : 'stable'),
            'change_speed' => round($slope, 3),
        ];
    }

    public function maximumConsecutiveAbsences(iterable $statuses): int
    {
        $maximum = 0;
        $current = 0;
        foreach ($statuses as $status) {
            $current = $status === 'absent' ? $current + 1 : 0;
            $maximum = max($maximum, $current);
        }

        return $maximum;
    }

    public function project(
        int $observedPresent,
        int $observedExpected,
        int $remainingExpected,
        float $futureRate,
        float $targetRate,
    ): array {
        $futurePresent = (int) round($remainingExpected * max(0, min(100, $futureRate)) / 100);
        $finalExpected = $observedExpected + $remainingExpected;
        $finalPresent = $observedPresent + $futurePresent;
        $projectedRate = $this->rate($finalPresent, $finalExpected);
        $required = max(0, (int) ceil(($targetRate / 100) * $finalExpected - $observedPresent));

        return [
            'projected_rate' => $projectedRate,
            'projected_present' => $finalPresent,
            'projected_expected' => $finalExpected,
            'target_rate' => round($targetRate, 2),
            'gap' => $this->gap($projectedRate, $targetRate),
            'required_future_attendances' => $required,
            'additional_attendances_needed' => max(0, $required - $futurePresent),
            'target_is_mathematically_reachable' => $required <= $remainingExpected,
            'assumption' => 'Tasa futura uniforme aplicada a los registros esperados restantes.',
        ];
    }

    private function percentile(Collection $values, int $percentile): float
    {
        $position = ($percentile / 100) * ($values->count() - 1);
        $lower = (int) floor($position);
        $upper = (int) ceil($position);
        if ($lower === $upper) {
            return round((float) $values[$lower], 2);
        }
        $weight = $position - $lower;

        return round(((float) $values[$lower] * (1 - $weight)) + ((float) $values[$upper] * $weight), 2);
    }
}
