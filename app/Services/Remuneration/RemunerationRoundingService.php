<?php

namespace App\Services\Remuneration;

class RemunerationRoundingService
{
    public function clp(float|int|string|null $value): int
    {
        return (int) round((float) ($value ?? 0), 0, PHP_ROUND_HALF_UP);
    }

    /**
     * @param  array<int, float|int|string>  $percentages
     * @return array<int, int>
     */
    public function distribute(int $amount, array $percentages): array
    {
        if ($percentages === []) {
            return [];
        }

        $normalized = array_map(fn ($percentage) => (float) $percentage, $percentages);
        $totalPercentage = array_sum($normalized);
        if ($totalPercentage <= 0) {
            $normalized = array_fill(0, count($percentages), 100 / count($percentages));
            $totalPercentage = 100;
        }

        $parts = [];
        foreach ($normalized as $percentage) {
            $parts[] = $this->clp($amount * ($percentage / $totalPercentage));
        }

        $residual = $amount - array_sum($parts);
        $index = 0;
        while ($residual !== 0 && isset($parts[$index])) {
            $parts[$index] += $residual > 0 ? 1 : -1;
            $residual += $residual > 0 ? -1 : 1;
            $index = ($index + 1) % count($parts);
        }

        return $parts;
    }
}
