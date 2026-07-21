<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceFinancialParameter;
use App\Models\Attendance\AttendanceProjectionSetting;

class AttendanceFinancialImpactService
{
    public function __construct(private readonly AttendanceCalculationService $calculations) {}

    public function calculate(int $academicYearId, array $summary, float $projectedRate, ?string $date = null): array
    {
        $date ??= now()->toDateString();
        $parameters = AttendanceFinancialParameter::query()
            ->where('academic_year_id', $academicYearId)
            ->where('active', true)
            ->whereDate('valid_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date))
            ->orderByDesc('valid_from')
            ->get();

        if ($parameters->isEmpty()) {
            $legacy = AttendanceProjectionSetting::query()->where('academic_year_id', $academicYearId)->first();
            if ($legacy && (float) $legacy->monthly_unit_value > 0) {
                $parameters = collect([(object) [
                    'id' => null,
                    'name' => 'Configuración histórica de asistencia',
                    'subsidy_type' => 'general',
                    'unit_value' => $legacy->monthly_unit_value,
                    'attendance_factor' => $legacy->attendance_factor,
                    'currency' => $legacy->currency,
                    'valid_from' => $legacy->valid_from,
                    'source_reference' => $legacy->configuration_source,
                    'assumptions' => 'Parámetro heredado del módulo de proyección.',
                ]]);
            }
        }

        $currentRate = (float) ($summary['attendance_rate'] ?? 0);
        $expected = (int) ($summary['expected'] ?? 0);
        $studentDays = $expected > 0 ? $expected : 0;
        $rows = $parameters->map(function ($parameter) use ($currentRate, $projectedRate, $studentDays) {
            $unit = (float) $parameter->unit_value * (float) $parameter->attendance_factor;
            $current = $studentDays * ($currentRate / 100) * $unit;
            $projected = $studentDays * ($projectedRate / 100) * $unit;
            $onePoint = $studentDays * 0.01 * $unit;

            return [
                'id' => $parameter->id,
                'name' => $parameter->name,
                'subsidy_type' => $parameter->subsidy_type,
                'currency' => $parameter->currency,
                'unit_value' => (float) $parameter->unit_value,
                'attendance_factor' => (float) $parameter->attendance_factor,
                'current_estimate' => round($current),
                'projected_estimate' => round($projected),
                'difference' => round($projected - $current),
                'impact_per_point' => round($onePoint),
                'valid_from' => $parameter->valid_from
                    ? (is_string($parameter->valid_from) ? $parameter->valid_from : $parameter->valid_from->format('Y-m-d'))
                    : null,
                'source_reference' => $parameter->source_reference,
                'assumptions' => $parameter->assumptions,
            ];
        });

        return [
            'available' => $rows->isNotEmpty(),
            'current_rate' => $currentRate,
            'projected_rate' => round($projectedRate, 2),
            'parameters' => $rows->values(),
            'totals' => [
                'current_estimate' => (int) $rows->sum('current_estimate'),
                'projected_estimate' => (int) $rows->sum('projected_estimate'),
                'difference' => (int) $rows->sum('difference'),
                'impact_per_point' => (int) $rows->sum('impact_per_point'),
            ],
            'warning' => 'Estimación referencial basada en los parámetros vigentes. No reemplaza el cálculo oficial de subvenciones.',
        ];
    }
}
