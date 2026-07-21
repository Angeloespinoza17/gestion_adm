<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceRiskLevel;
use Illuminate\Support\Collection;

class AttendanceRiskService
{
    public function levels(int $academicYearId): Collection
    {
        $levels = AttendanceRiskLevel::query()
            ->where(fn ($query) => $query->where('academic_year_id', $academicYearId)->orWhereNull('academic_year_id'))
            ->where('active', true)
            ->orderByRaw('CASE WHEN academic_year_id IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('priority')
            ->get();

        $levels = $levels->unique('slug')->values();

        return $levels->isNotEmpty() ? $levels : collect($this->fallbackLevels())->map(fn ($level) => (object) $level);
    }

    public function classify(?float $rate, int $academicYearId): array
    {
        if ($rate === null) {
            return ['slug' => 'no_data', 'name' => 'Sin datos', 'color' => '#64748b', 'icon' => 'bx-help-circle', 'priority' => 99];
        }

        $match = $this->levels($academicYearId)->first(fn ($level) => $rate >= (float) $level->minimum_rate && $rate <= (float) $level->maximum_rate);

        return $match ? [
            'id' => $match->id ?? null,
            'slug' => $match->slug,
            'name' => $match->name,
            'color' => $match->color,
            'icon' => $match->icon,
            'priority' => (int) $match->priority,
        ] : ['slug' => 'unclassified', 'name' => 'Sin clasificación', 'color' => '#64748b', 'icon' => 'bx-help-circle', 'priority' => 98];
    }

    private function fallbackLevels(): array
    {
        return [
            ['id' => null, 'slug' => 'high', 'name' => 'Riesgo alto', 'minimum_rate' => 0, 'maximum_rate' => 84.99, 'color' => '#dc3545', 'icon' => 'bx-error', 'priority' => 4],
            ['id' => null, 'slug' => 'moderate', 'name' => 'Riesgo moderado', 'minimum_rate' => 85, 'maximum_rate' => 89.99, 'color' => '#d97706', 'icon' => 'bx-error-circle', 'priority' => 3],
            ['id' => null, 'slug' => 'low', 'name' => 'Riesgo leve', 'minimum_rate' => 90, 'maximum_rate' => 94.99, 'color' => '#2563eb', 'icon' => 'bx-info-circle', 'priority' => 2],
            ['id' => null, 'slug' => 'none', 'name' => 'Sin riesgo', 'minimum_rate' => 95, 'maximum_rate' => 100, 'color' => '#198754', 'icon' => 'bx-check-shield', 'priority' => 1],
        ];
    }
}
