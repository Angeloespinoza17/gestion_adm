<?php

namespace App\Services\Accounting;

use App\Models\AcademicYear;
use App\Models\Accounting\AccountingIncome;
use App\Models\Accounting\AccountingSubsidyAllocation;
use App\Models\Accounting\AccountingSubsidySettlement;
use App\Models\Accounting\AccountingSubsidySettlementLine;
use App\Models\Attendance\MonthlyAttendanceImport;
use App\Models\Attendance\MonthlyAttendanceImportRow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AttendanceSubsidyReconciliationService
{
    private const CALCULABLE_FAMILIES = ['normal', 'sep_prioritario', 'sep_preferente'];

    /** @param array<string,mixed> $options */
    public function calculate(CarbonImmutable $paymentPeriod, array $options = []): array
    {
        $options = $this->options($options);
        $schedule = $this->scheduleFor($paymentPeriod);
        $requiredPeriods = $this->regulatoryPeriods($paymentPeriod);
        $imports = $this->attendanceImports($requiredPeriods);
        $monthly = $this->monthlyAttendance($imports);
        $foundPeriods = collect($requiredPeriods)->filter(fn (string $period): bool => $monthly->has($period))->values();
        $missingPeriods = collect($requiredPeriods)->diff($foundPeriods)->values();
        $latest = $foundPeriods->last() ? $monthly->get($foundPeriods->last(), []) : [];
        $reference = $this->referenceCounts(collect(data_get($latest, 'levels', []))->values());
        [$concentrationBand, $concentrationBasis] = $this->concentrationBand($options['concentration_band'], $reference);
        $actual = $this->actualSettlementData($paymentPeriod, $options, $concentrationBand);

        if (! $schedule || $monthly->isEmpty()) {
            return $this->emptyResult(
                $paymentPeriod,
                $options,
                $schedule,
                $requiredPeriods,
                $foundPeriods,
                $missingPeriods,
                $actual,
            );
        }

        $useValue = (float) $schedule['use_value'];
        $levelKeys = $monthly
            ->flatMap(fn (array $month): array => array_keys($month['levels']))
            ->unique()
            ->sortBy(fn (string $key): int => $this->levelDefinition($key)['order'])
            ->values();

        $byLevel = $levelKeys->map(function (string $key) use (
            $monthly,
            $foundPeriods,
            $actual,
            $options,
            $concentrationBand,
            $useValue,
            $paymentPeriod,
        ): array {
            $definition = $this->levelDefinition($key);
            $observations = $foundPeriods
                ->map(fn (string $period) => data_get($monthly->get($period), "levels.{$key}"))
                ->filter();
            $monthCount = $observations->count();
            $average = fn (string $field): float => $monthCount > 0
                ? round((float) $observations->avg($field), 4)
                : 0.0;
            $attendance = $average('attendance_equivalent');
            $enrollment = $average('enrollment');
            $priorityAttendance = $average('priority_attendance_equivalent');
            $preferentialAttendance = $average('preferential_attendance_equivalent');
            $priorityEnrollment = $average('priority');
            $preferentialEnrollment = $average('preferential');
            $generalFactor = (float) config('accounting_subsidy_estimation.general_use_factors.'.($options['jec'] ? 'jec' : 'sin_jec').".{$definition['rate_group']}", 0);
            $priorityFactor = (float) config("accounting_subsidy_estimation.sep_use_factors.{$options['sep_category']}.prioritario.{$definition['sep_group']}", 0);
            $preferentialFactor = (float) config("accounting_subsidy_estimation.sep_use_factors.{$options['sep_category']}.preferente.{$definition['sep_group']}", 0);
            $concentrationFactor = (float) config("accounting_subsidy_estimation.concentration_use_factors.{$concentrationBand}.{$definition['sep_group']}", 0);
            $gratuityFactor = $options['include_gratuity']
                ? (float) config('accounting_subsidy_estimation.gratuity_use_factor', 0)
                : 0.0;
            $pensionFactor = $paymentPeriod->gte(CarbonImmutable::parse(config('accounting_subsidy_estimation.pension_reform.valid_from')))
                ? (float) config("accounting_subsidy_estimation.pension_reform.use_factors.{$definition['rate_group']}", 0)
                : 0.0;

            $components = [
                'general' => round($attendance * $generalFactor * $useValue, 2),
                'gratuity' => round($attendance * $gratuityFactor * $useValue, 2),
                'pension_reform' => round($attendance * $pensionFactor * $useValue, 2),
                'sep_priority' => round($priorityAttendance * $priorityFactor * $useValue, 2),
                'sep_preferential' => round($preferentialAttendance * $preferentialFactor * $useValue, 2),
                'sep_concentration' => round($attendance * $concentrationFactor * $useValue, 2),
            ];
            $fullAttendanceComponents = [
                'general' => round($enrollment * $generalFactor * $useValue, 2),
                'gratuity' => round($enrollment * $gratuityFactor * $useValue, 2),
                'pension_reform' => round($enrollment * $pensionFactor * $useValue, 2),
                'sep_priority' => round($priorityEnrollment * $priorityFactor * $useValue, 2),
                'sep_preferential' => round($preferentialEnrollment * $preferentialFactor * $useValue, 2),
                'sep_concentration' => round($enrollment * $concentrationFactor * $useValue, 2),
            ];
            $expected = round((float) array_sum($components), 2);
            $modeledFullAttendance = round((float) array_sum($fullAttendanceComponents), 2);
            $liquidated = round((float) ($actual['by_level'][$definition['order']] ?? 0), 2);
            $liquidatedTotal = round((float) ($actual['gross_by_level'][$definition['order']] ?? 0), 2);
            $liquidationProjection = $this->projectFullAttendanceFromLiquidation(
                $actualComponents = $actual['components_by_level'][$definition['order']] ?? [],
                $enrollment > 0 ? $attendance / $enrollment : null,
                $priorityEnrollment > 0 ? $priorityAttendance / $priorityEnrollment : null,
                $preferentialEnrollment > 0 ? $preferentialAttendance / $preferentialEnrollment : null,
            );

            return [
                'key' => $key,
                'label' => $definition['label'],
                'cycle' => $definition['cycle'],
                'order' => $definition['order'],
                'months_with_data' => $monthCount,
                'enrollment_average' => $enrollment,
                'attendance_equivalent' => $attendance,
                'attendance_rate' => $enrollment > 0 ? round(($attendance / $enrollment) * 100, 2) : null,
                'priority_attendance_equivalent' => $priorityAttendance,
                'preferential_attendance_equivalent' => $preferentialAttendance,
                'priority_enrollment_average' => $priorityEnrollment,
                'preferential_enrollment_average' => $preferentialEnrollment,
                'components' => $components,
                'full_attendance_components' => $fullAttendanceComponents,
                'modeled_full_by_family' => [
                    'normal' => round($fullAttendanceComponents['general'] + $fullAttendanceComponents['gratuity'] + $fullAttendanceComponents['pension_reform'], 2),
                    'sep_prioritario' => round($fullAttendanceComponents['sep_priority'] + $fullAttendanceComponents['sep_concentration'], 2),
                    'sep_preferente' => round($fullAttendanceComponents['sep_preferential'], 2),
                ],
                'liquidated_by_family' => collect($actualComponents)
                    ->map(fn (array $concepts): float => round((float) array_sum($concepts), 2))
                    ->all(),
                'liquidation_projection_by_family' => $liquidationProjection['by_family'],
                'unprojectable_liquidation_families' => $liquidationProjection['unprojectable_families'],
                'modeled_full_attendance_amount' => $modeledFullAttendance,
                'modeled_attendance_reduction_amount' => max(0, round($modeledFullAttendance - $expected, 2)),
                'expected_amount' => $expected,
                'liquidated_amount' => $liquidated,
                'liquidated_total_amount' => $liquidatedTotal,
                'excluded_liquidated_amount' => max(0, round($liquidatedTotal - $liquidated, 2)),
                'difference_amount' => round($liquidated - $expected, 2),
            ];
        })->values();

        $expectedByFamily = [
            'normal' => round((float) $byLevel->sum(fn (array $row): float => $row['components']['general'] + $row['components']['gratuity'] + $row['components']['pension_reform']), 2),
            'sep_prioritario' => round((float) $byLevel->sum(fn (array $row): float => $row['components']['sep_priority'] + $row['components']['sep_concentration']), 2),
            'sep_preferente' => round((float) $byLevel->sum(fn (array $row): float => $row['components']['sep_preferential']), 2),
        ];
        $fullAttendanceByFamily = [
            'normal' => round((float) $byLevel->sum(fn (array $row): float => $row['full_attendance_components']['general'] + $row['full_attendance_components']['gratuity'] + $row['full_attendance_components']['pension_reform']), 2),
            'sep_prioritario' => round((float) $byLevel->sum(fn (array $row): float => $row['full_attendance_components']['sep_priority'] + $row['full_attendance_components']['sep_concentration']), 2),
            'sep_preferente' => round((float) $byLevel->sum(fn (array $row): float => $row['full_attendance_components']['sep_preferential']), 2),
        ];
        $liquidationProjectionByFamily = collect(self::CALCULABLE_FAMILIES)
            ->mapWithKeys(fn (string $family): array => [
                $family => round((float) $byLevel->sum(fn (array $row): float => (float) ($row['liquidation_projection_by_family'][$family] ?? 0)), 2),
            ])
            ->all();
        $unprojectableFamilies = $byLevel
            ->flatMap(fn (array $row): array => $row['unprojectable_liquidation_families'])
            ->merge(collect($actual['unallocated_by_family'] ?? [])->filter(fn (float $amount): bool => abs($amount) > 1)->keys())
            ->unique()
            ->values();
        $adjustedFamilies = collect(self::CALCULABLE_FAMILIES)
            ->filter(function (string $family) use ($actual, $fullAttendanceByFamily, $liquidationProjectionByFamily, $unprojectableFamilies): bool {
                $liquidated = (float) ($actual['comparable_by_family'][$family] ?? 0);
                $modeledFull = (float) ($fullAttendanceByFamily[$family] ?? 0);
                $projectedFull = (float) ($liquidationProjectionByFamily[$family] ?? 0);

                return $liquidated > max(1, $modeledFull * 1.01)
                    && $projectedFull > $liquidated
                    && ! $unprojectableFamilies->contains($family);
            })
            ->values();
        $inconsistentFamilies = collect(self::CALCULABLE_FAMILIES)
            ->filter(fn (string $family): bool => (float) ($actual['comparable_by_family'][$family] ?? 0)
                > max(1, (float) ($fullAttendanceByFamily[$family] ?? 0) * 1.01))
            ->values();
        $reviewFamilies = $inconsistentFamilies->diff($adjustedFamilies)->values();
        $byLevel = $byLevel->map(function (array $row) use ($adjustedFamilies, $reviewFamilies, $actual): array {
            $fullAttendance = round((float) collect(self::CALCULABLE_FAMILIES)->sum(function (string $family) use ($row, $adjustedFamilies, $reviewFamilies): float {
                if ($adjustedFamilies->contains($family)) {
                    return (float) ($row['liquidation_projection_by_family'][$family] ?? $row['modeled_full_by_family'][$family] ?? 0);
                }
                if ($reviewFamilies->contains($family)) {
                    return max(
                        (float) ($row['modeled_full_by_family'][$family] ?? 0),
                        (float) ($row['liquidated_by_family'][$family] ?? 0),
                    );
                }

                return (float) ($row['modeled_full_by_family'][$family] ?? 0);
            }), 2);
            $hasLiquidation = (int) ($actual['settlement_count'] ?? 0) > 0 && (float) $row['liquidated_amount'] > 0;
            $lossReference = $hasLiquidation ? (float) $row['liquidated_amount'] : (float) $row['expected_amount'];

            return [
                ...$row,
                'full_attendance_amount' => $fullAttendance,
                'attendance_loss_amount' => $this->lossAgainstFullAttendance($fullAttendance, $lossReference),
                'baseline_source' => ($adjustedFamilies->isEmpty() && $reviewFamilies->isEmpty()) ? 'normative_model' : 'mixed',
                'baseline_adjusted_families' => $adjustedFamilies->all(),
                'baseline_review_families' => $reviewFamilies->all(),
            ];
        });
        $familyKeys = collect(array_keys($expectedByFamily))
            ->merge(array_keys($actual['by_family']))
            ->merge(array_keys($actual['income_by_family']))
            ->unique()
            ->values();
        $bySubsidy = $familyKeys->map(function (string $family) use ($expectedByFamily, $fullAttendanceByFamily, $liquidationProjectionByFamily, $adjustedFamilies, $reviewFamilies, $unprojectableFamilies, $actual): array {
            $calculable = in_array($family, self::CALCULABLE_FAMILIES, true);
            $expected = $calculable ? round((float) ($expectedByFamily[$family] ?? 0), 2) : null;
            $modeledFullAttendance = $calculable ? round((float) ($fullAttendanceByFamily[$family] ?? 0), 2) : null;
            $liquidated = round((float) (
                $calculable
                    ? ($actual['comparable_by_family'][$family] ?? 0)
                    : ($actual['by_family'][$family] ?? 0)
            ), 2);
            $liquidatedTotal = round((float) ($actual['by_family'][$family] ?? 0), 2);
            $income = round((float) ($actual['income_by_family'][$family] ?? 0), 2);
            $baselineAdjusted = $calculable && $adjustedFamilies->contains($family);
            $baselineRequiresReview = $calculable && $reviewFamilies->contains($family);
            $fullAttendance = ! $calculable
                ? null
                : round((float) ($baselineAdjusted
                    ? ($liquidationProjectionByFamily[$family] ?? $modeledFullAttendance)
                    : ($baselineRequiresReview ? max($liquidated, (float) $modeledFullAttendance) : $modeledFullAttendance)), 2);
            $hasLiquidation = (int) ($actual['settlement_count'] ?? 0) > 0 && $liquidated > 0;
            $lossReference = $hasLiquidation ? $liquidated : $expected;

            return [
                'key' => $family,
                'label' => $this->familyLabel($family),
                'calculable_from_attendance' => $calculable,
                'full_attendance_amount' => $fullAttendance,
                'modeled_full_attendance_amount' => $modeledFullAttendance,
                'modeled_attendance_reduction_amount' => $modeledFullAttendance === null || $expected === null ? null : max(0, round($modeledFullAttendance - $expected, 2)),
                'attendance_loss_amount' => $fullAttendance === null || $lossReference === null ? null : $this->lossAgainstFullAttendance($fullAttendance, $lossReference),
                'expected_amount' => $expected,
                'liquidated_amount' => $liquidated,
                'liquidated_total_amount' => $liquidatedTotal,
                'excluded_liquidated_amount' => max(0, round($liquidatedTotal - $liquidated, 2)),
                'difference_amount' => $expected === null ? null : round($liquidated - $expected, 2),
                'income_amount' => $income,
                'income_difference_amount' => $expected === null ? null : round($income - $expected, 2),
                'baseline_source' => $baselineAdjusted
                    ? 'liquidation_scaled_by_attendance'
                    : ($baselineRequiresReview ? 'incomplete_population_floor' : 'normative_model'),
                'baseline_requires_review' => $baselineRequiresReview || ($calculable && $unprojectableFamilies->contains($family)),
                'note' => $calculable
                    ? ($baselineAdjusted
                        ? 'La nómina local no cubre la población liquidada; el máximo se proyecta desde la liquidación oficial y la tasa acumulada.'
                        : ($baselineRequiresReview
                            ? 'La población SEP de la carga local es insuficiente para proyectar el 100%; se muestra como mínimo el monto liquidado y se exige revisar la nómina.'
                            : null))
                    : 'La normativa exige antecedentes adicionales a la asistencia mensual.',
            ];
        });

        $expectedTotal = round((float) array_sum($expectedByFamily), 2);
        $modeledFullAttendanceTotal = round((float) array_sum($fullAttendanceByFamily), 2);
        $fullAttendanceTotal = round((float) $bySubsidy->sum(fn (array $row): float => (float) ($row['full_attendance_amount'] ?? 0)), 2);
        $liquidatedTotal = round((float) collect($actual['comparable_by_family'])->sum(), 2);
        $attendanceLossReference = (int) ($actual['settlement_count'] ?? 0) > 0 ? $liquidatedTotal : $expectedTotal;
        $attendanceLossTotal = $this->lossAgainstFullAttendance($fullAttendanceTotal, $attendanceLossReference);
        $modeledAttendanceReductionTotal = max(0, round($modeledFullAttendanceTotal - $expectedTotal, 2));
        $difference = round($liquidatedTotal - $expectedTotal, 2);
        $registeredIncomeTotal = round((float) $actual['income_total'], 2);
        $registeredComparableIncomeTotal = round((float) $actual['comparable_income_total'], 2);
        $incomeReferenceTotal = (int) ($actual['settlement_count'] ?? 0) > 0 ? $liquidatedTotal : $expectedTotal;
        $incomeDifference = round($registeredComparableIncomeTotal - $incomeReferenceTotal, 2);
        $incomeModelDifference = round($registeredComparableIncomeTotal - $expectedTotal, 2);
        $enrollmentAverage = (float) $byLevel->sum('enrollment_average');
        $attendanceEquivalent = (float) $byLevel->sum('attendance_equivalent');
        $coverage = count($requiredPeriods) > 0 ? round(($foundPeriods->count() / count($requiredPeriods)) * 100, 2) : 0;
        $liquidationWithinTolerance = abs($difference) <= max(1, $expectedTotal * 0.01);
        $incomeWithinTolerance = abs($incomeDifference) <= max(1, $incomeReferenceTotal * 0.01);
        $incomeStatus = $actual['income_count'] === 0
            ? 'sin_ingreso'
            : ($incomeWithinTolerance ? 'cuadrado' : 'diferencia');
        $status = match (true) {
            $coverage < 100 => 'incompleto',
            $incomeStatus === 'sin_ingreso' => 'sin_ingreso',
            $liquidationWithinTolerance && $incomeWithinTolerance => 'cuadrado',
            default => 'diferencia',
        };

        return [
            'available' => true,
            'status' => $status,
            'payment_period' => $paymentPeriod->format('Y-m'),
            'metrics' => [
                'full_attendance_total' => $fullAttendanceTotal,
                'attendance_loss_total' => $attendanceLossTotal,
                'attendance_loss_percentage' => $fullAttendanceTotal > 0 ? round(($attendanceLossTotal / $fullAttendanceTotal) * 100, 2) : null,
                'modeled_full_attendance_total' => $modeledFullAttendanceTotal,
                'modeled_attendance_reduction_total' => $modeledAttendanceReductionTotal,
                'baseline_source' => ($adjustedFamilies->isEmpty() && $reviewFamilies->isEmpty()) ? 'normative_model' : 'mixed',
                'baseline_adjusted_families' => $adjustedFamilies->all(),
                'baseline_review_families' => $reviewFamilies->all(),
                'expected_total' => $expectedTotal,
                'liquidated_gross_total' => round((float) collect($actual['by_family'])->sum(), 2),
                'liquidated_total' => $liquidatedTotal,
                'difference_total' => $difference,
                'difference_percentage' => $expectedTotal > 0 ? round(($difference / $expectedTotal) * 100, 2) : null,
                'excluded_liquidated_total' => round((float) $actual['excluded_total'], 2),
                'actual_unallocated_total' => round((float) $actual['unallocated_total'], 2),
                'registered_income_total' => $registeredIncomeTotal,
                'registered_comparable_income_total' => $registeredComparableIncomeTotal,
                'registered_income_unallocated_total' => round((float) $actual['unallocated_comparable_income_total'], 2),
                'excluded_registered_income_total' => round((float) $actual['excluded_income_total'], 2),
                'income_reference_total' => $incomeReferenceTotal,
                'income_reference_source' => (int) ($actual['settlement_count'] ?? 0) > 0 ? 'liquidation' : 'attendance_model',
                'income_difference_total' => $incomeDifference,
                'income_difference_percentage' => $incomeReferenceTotal > 0 ? round(($incomeDifference / $incomeReferenceTotal) * 100, 2) : null,
                'income_model_difference_total' => $incomeModelDifference,
                'settlement_count' => (int) $actual['settlement_count'],
                'income_records_count' => (int) $actual['income_count'],
                'income_status' => $incomeStatus,
                'attendance_rate' => $enrollmentAverage > 0 ? round(($attendanceEquivalent / $enrollmentAverage) * 100, 2) : null,
                'attendance_equivalent' => round($attendanceEquivalent, 2),
                'enrollment_average' => round($enrollmentAverage, 2),
                'priority_students' => $reference['priority'],
                'preferential_students' => $reference['preferential'],
                'pie_students' => $reference['pie'],
                'coverage_percentage' => $coverage,
            ],
            'window' => [
                'required_periods' => $requiredPeriods,
                'found_periods' => $foundPeriods->all(),
                'missing_periods' => $missingPeriods->all(),
                'source_imports' => $imports->map(fn (MonthlyAttendanceImport $import): array => [
                    'id' => $import->id,
                    'period' => sprintf('%04d-%02d', $import->school_year, $import->month),
                    'filename' => $import->original_filename,
                ])->values()->all(),
            ],
            'assumptions' => [
                'jec' => $options['jec'],
                'sep_category' => $options['sep_category'],
                'include_gratuity' => $options['include_gratuity'],
                'concentration_band' => $concentrationBand,
                'concentration_basis' => $concentrationBasis,
                'concentration_reference_percentage' => $reference['enrollment'] > 0
                    ? round(($reference['priority'] / $reference['enrollment']) * 100, 2)
                    : null,
                'use_value' => $useValue,
                'rate_label' => $schedule['label'],
                'rate_valid_from' => $schedule['valid_from'],
            ],
            'by_level' => $byLevel->all(),
            'by_subsidy' => $bySubsidy->all(),
            'income_records' => $actual['income_records'],
            'warnings' => array_values(array_merge(
                $this->warnings($missingPeriods, $monthly, $options, $concentrationBasis, $actual),
                $adjustedFamilies->isNotEmpty()
                    ? ['La población SEP de la carga de asistencia no cubre toda la liquidación oficial. Para evitar una falsa “sobreliquidación”, esas familias se proyectaron a 100% desde el monto MINEDUC y la tasa acumulada.']
                    : [],
                $reviewFamilies->isNotEmpty()
                    ? ['No fue posible proyectar a 100% '.implode(', ', $reviewFamilies->map(fn (string $family): string => $this->familyLabel($family))->all()).' porque la nómina local no contiene la población liquidada. Se muestra un mínimo y la pérdida queda pendiente de validar.']
                    : [],
            )),
            'sources' => config('accounting_subsidy_estimation.sources', []),
            'disclaimer' => 'Estimación de control interno. La merma por inasistencia compara el cálculo reglamentario con un escenario teórico de 100% de asistencia; no representa por sí sola un monto exigible. La liquidación oficial MINEDUC prevalece y puede incluir zona, ruralidad, reliquidaciones, topes, PIE y otras glosas no derivables sólo desde asistencia.',
        ];
    }

    /** @param array<string,mixed> $options */
    private function options(array $options): array
    {
        $defaults = config('accounting_subsidy_estimation.defaults', []);

        return [
            'jec' => filter_var($options['jec'] ?? $defaults['jec'] ?? true, FILTER_VALIDATE_BOOL),
            'sep_category' => in_array($options['sep_category'] ?? null, ['autonomo', 'emergente'], true)
                ? $options['sep_category']
                : ($defaults['sep_category'] ?? 'autonomo'),
            'include_gratuity' => filter_var($options['include_gratuity'] ?? $defaults['include_gratuity'] ?? true, FILTER_VALIDATE_BOOL),
            'concentration_band' => in_array($options['concentration_band'] ?? null, ['auto', 'none', '15_30', '30_45', '45_60', '60_plus'], true)
                ? $options['concentration_band']
                : ($defaults['concentration_band'] ?? 'auto'),
        ];
    }

    private function scheduleFor(CarbonImmutable $period): ?array
    {
        return collect(config('accounting_subsidy_estimation.schedules', []))
            ->filter(function (array $schedule) use ($period): bool {
                $from = CarbonImmutable::parse($schedule['valid_from'])->startOfMonth();
                $to = isset($schedule['valid_to']) ? CarbonImmutable::parse($schedule['valid_to'])->endOfMonth() : null;

                return $period->gte($from) && (! $to || $period->lte($to));
            })
            ->sortByDesc('valid_from')
            ->first();
    }

    /** @return array<int,string> */
    private function regulatoryPeriods(CarbonImmutable $paymentPeriod): array
    {
        $academicYear = AcademicYear::query()->where('year', $paymentPeriod->year)->first();
        $schoolStart = $academicYear?->starts_at
            ? CarbonImmutable::parse($academicYear->starts_at)->startOfMonth()
            : CarbonImmutable::create($paymentPeriod->year, 3, 1);
        $monthsSinceStart = $schoolStart->diffInMonths($paymentPeriod, false);

        if ($monthsSinceStart <= 0) {
            $previousYear = AcademicYear::query()->where('year', $paymentPeriod->year - 1)->first();
            $from = $previousYear?->starts_at
                ? CarbonImmutable::parse($previousYear->starts_at)->startOfMonth()
                : CarbonImmutable::create($paymentPeriod->year - 1, 3, 1);
            $to = $previousYear?->ends_at
                ? CarbonImmutable::parse($previousYear->ends_at)->startOfMonth()
                : CarbonImmutable::create($paymentPeriod->year - 1, 12, 1);
            $periods = [];
            for ($cursor = $from; $cursor->lte($to); $cursor = $cursor->addMonth()) {
                $periods[] = $cursor->format('Y-m');
            }

            return $periods;
        }

        $count = min(3, $monthsSinceStart);

        return collect(range($count, 1))
            ->map(fn (int $monthsBack): string => $paymentPeriod->subMonths($monthsBack)->format('Y-m'))
            ->values()
            ->all();
    }

    /** @param array<int,string> $periods */
    private function attendanceImports(array $periods): Collection
    {
        if ($periods === []) {
            return collect();
        }

        return MonthlyAttendanceImport::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($periods): void {
                foreach ($periods as $period) {
                    [$year, $month] = array_map('intval', explode('-', $period));
                    $query->orWhere(fn (Builder $inner) => $inner
                        ->where('school_year', $year)
                        ->where('month', $month));
                }
            })
            ->orderBy('school_year')
            ->orderBy('month')
            ->get(['id', 'school_year', 'month', 'original_filename']);
    }

    /** @param Collection<int,MonthlyAttendanceImport> $imports */
    private function monthlyAttendance(Collection $imports): Collection
    {
        if ($imports->isEmpty()) {
            return collect();
        }

        $importsById = $imports->keyBy('id');

        return MonthlyAttendanceImportRow::query()
            ->whereIn('monthly_attendance_import_id', $imports->pluck('id'))
            ->get([
                'monthly_attendance_import_id', 'source_course_name', 'present_days', 'class_days',
                'is_sep_priority', 'is_sep_preferential', 'is_pie',
            ])
            ->groupBy('monthly_attendance_import_id')
            ->mapWithKeys(function (Collection $rows, int|string $importId) use ($importsById): array {
                $import = $importsById->get((int) $importId);
                $levels = [];
                $unmapped = 0;
                $invalidAttendance = 0;

                foreach ($rows as $row) {
                    $definition = $this->parseCourse((string) $row->source_course_name);
                    if (! $definition) {
                        $unmapped++;

                        continue;
                    }
                    $classDays = (float) $row->class_days;
                    if ($classDays <= 0) {
                        $invalidAttendance++;

                        continue;
                    }
                    $key = $definition['key'];
                    $levels[$key] ??= [
                        'enrollment' => 0,
                        'attendance_equivalent' => 0.0,
                        'priority' => 0,
                        'preferential' => 0,
                        'pie' => 0,
                        'priority_attendance_equivalent' => 0.0,
                        'preferential_attendance_equivalent' => 0.0,
                    ];
                    $attendanceFactor = min(1, max(0, (float) $row->present_days / $classDays));
                    $levels[$key]['enrollment']++;
                    $levels[$key]['attendance_equivalent'] += $attendanceFactor;
                    if ($row->is_sep_priority) {
                        $levels[$key]['priority']++;
                        $levels[$key]['priority_attendance_equivalent'] += $attendanceFactor;
                    }
                    if ($row->is_sep_preferential) {
                        $levels[$key]['preferential']++;
                        $levels[$key]['preferential_attendance_equivalent'] += $attendanceFactor;
                    }
                    if ($row->is_pie) {
                        $levels[$key]['pie']++;
                    }
                }

                $period = sprintf('%04d-%02d', $import->school_year, $import->month);

                return [$period => [
                    'import_id' => $import->id,
                    'levels' => $levels,
                    'source_rows' => $rows->count(),
                    'unmapped_rows' => $unmapped,
                    'invalid_attendance_rows' => $invalidAttendance,
                ]];
            })
            ->sortKeys();
    }

    /** @return array<string,mixed>|null */
    private function parseCourse(string $course): ?array
    {
        $normalized = Str::of(Str::ascii($course))->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();
        if (preg_match('/\b(?:nt\s*1|pre\s*kinder|prekinder|primer\s+nivel\s+transicion)\b/', $normalized)) {
            return $this->levelDefinition('nt1');
        }
        if (preg_match('/\b(?:nt\s*2|kinder|segundo\s+nivel\s+transicion)\b/', $normalized)) {
            return $this->levelDefinition('nt2');
        }
        if (preg_match('/\b([1-8])\s*(?:o\s*)?basico\b/', $normalized, $matches)) {
            return $this->levelDefinition('basica_'.(int) $matches[1]);
        }
        if (preg_match('/\b([1-4])\s*(?:o\s*)?medio\b/', $normalized, $matches)) {
            return $this->levelDefinition('media_'.(int) $matches[1]);
        }

        return null;
    }

    /** @return array<string,mixed> */
    private function levelDefinition(string $key): array
    {
        if ($key === 'nt1') {
            return ['key' => $key, 'label' => 'NT1', 'order' => 1, 'cycle' => 'parvularia', 'rate_group' => 'parvularia', 'sep_group' => 'inicial'];
        }
        if ($key === 'nt2') {
            return ['key' => $key, 'label' => 'NT2', 'order' => 2, 'cycle' => 'parvularia', 'rate_group' => 'parvularia', 'sep_group' => 'inicial'];
        }
        if (str_starts_with($key, 'basica_')) {
            $grade = (int) Str::after($key, 'basica_');

            return [
                'key' => $key,
                'label' => $grade.'° básico',
                'order' => $grade + 2,
                'cycle' => 'basica',
                'rate_group' => $grade <= 6 ? 'basica_1_6' : 'basica_7_8',
                'sep_group' => $grade <= 6 ? 'inicial' : 'superior',
            ];
        }
        $grade = (int) Str::after($key, 'media_');

        return [
            'key' => $key,
            'label' => $grade.'° medio',
            'order' => $grade + 10,
            'cycle' => 'media',
            'rate_group' => 'media_hc',
            'sep_group' => 'superior',
        ];
    }

    /** @param Collection<int,array<string,mixed>> $levels */
    private function referenceCounts(Collection $levels): array
    {
        return [
            'enrollment' => (int) $levels->sum('enrollment'),
            'priority' => (int) $levels->sum('priority'),
            'preferential' => (int) $levels->sum('preferential'),
            'pie' => (int) $levels->sum('pie'),
        ];
    }

    /** @return array{0:string,1:string} */
    private function concentrationBand(string $requested, array $reference): array
    {
        if ($requested !== 'auto') {
            return [$requested, 'manual'];
        }

        $percentage = $reference['enrollment'] > 0 ? ($reference['priority'] / $reference['enrollment']) * 100 : 0;
        $band = match (true) {
            $percentage >= 60 => '60_plus',
            $percentage >= 45 => '45_60',
            $percentage >= 30 => '30_45',
            $percentage >= 15 => '15_30',
            default => 'none',
        };

        return [$band, 'estimación con el último mes disponible; la banda oficial usa marzo-diciembre del año anterior'];
    }

    /** @return array<string,mixed> */
    /** @param array<string,mixed> $options */
    private function actualSettlementData(CarbonImmutable $paymentPeriod, array $options, string $concentrationBand): array
    {
        $settlements = AccountingSubsidySettlement::query()
            ->with(['lines.allocations.educationLevel:id,order'])
            ->whereDate('period', $paymentPeriod)
            ->get();
        $byFamily = $settlements
            ->groupBy('subsidy_type')
            ->map(fn (Collection $items): float => round((float) $items->sum('net_amount'), 2))
            ->all();
        $calculableSettlements = $settlements->whereIn('subsidy_type', self::CALCULABLE_FAMILIES);
        $comparableLines = $calculableSettlements->flatMap(fn (AccountingSubsidySettlement $settlement): Collection => $settlement->lines
            ->filter(fn (AccountingSubsidySettlementLine $line): bool => $this->isComparableActualLine(
                $settlement,
                $line,
                $paymentPeriod,
                $options,
                $concentrationBand,
            )));
        $comparableByFamily = $calculableSettlements
            ->groupBy('subsidy_type')
            ->map(function (Collection $items) use ($paymentPeriod, $options, $concentrationBand): float {
                return round((float) $items->sum(function (AccountingSubsidySettlement $settlement) use ($paymentPeriod, $options, $concentrationBand): float {
                    $lines = $settlement->lines->filter(fn (AccountingSubsidySettlementLine $line): bool => $this->isComparableActualLine(
                        $settlement,
                        $line,
                        $paymentPeriod,
                        $options,
                        $concentrationBand,
                    ));

                    return (float) $lines->sum(fn (AccountingSubsidySettlementLine $line): float => $line->sign * (float) $line->amount);
                }), 2);
            })
            ->all();
        $comparableAllocationRows = $calculableSettlements
            ->flatMap(function (AccountingSubsidySettlement $settlement) use ($paymentPeriod, $options, $concentrationBand): Collection {
                return $settlement->lines
                    ->filter(fn (AccountingSubsidySettlementLine $line): bool => $line->education_allocable
                        && $this->isComparableActualLine($settlement, $line, $paymentPeriod, $options, $concentrationBand))
                    ->flatMap(function (AccountingSubsidySettlementLine $line) use ($settlement): Collection {
                        return $line->allocations
                            ->filter(fn (AccountingSubsidyAllocation $allocation): bool => $allocation->educationLevel?->order !== null)
                            ->map(fn (AccountingSubsidyAllocation $allocation): array => [
                                'order' => (int) $allocation->educationLevel->order,
                                'family' => (string) $settlement->subsidy_type,
                                'concept' => (string) $line->concept_code,
                                'amount' => $line->sign * (float) $allocation->amount,
                            ]);
                    });
            });
        $byLevel = $comparableAllocationRows
            ->groupBy('order')
            ->map(fn (Collection $items): float => round((float) $items->sum('amount'), 2))
            ->all();
        $allocatedByFamily = $comparableAllocationRows
            ->groupBy('family')
            ->map(fn (Collection $items): float => round((float) $items->sum('amount'), 2));
        $unallocatedByFamily = collect($comparableByFamily)
            ->mapWithKeys(fn (float $amount, string $family): array => [
                $family => round($amount - (float) ($allocatedByFamily[$family] ?? 0), 2),
            ])
            ->all();
        $componentsByLevel = $comparableAllocationRows
            ->groupBy('order')
            ->map(fn (Collection $levelRows): array => $levelRows
                ->groupBy('family')
                ->map(fn (Collection $familyRows): array => $familyRows
                    ->groupBy('concept')
                    ->map(fn (Collection $conceptRows): float => round((float) $conceptRows->sum('amount'), 2))
                    ->all())
                ->all())
            ->all();
        $grossByLevel = $settlements
            ->flatMap->lines
            ->filter(fn (AccountingSubsidySettlementLine $line): bool => $line->education_allocable)
            ->flatMap->allocations
            ->filter(fn (AccountingSubsidyAllocation $allocation): bool => $allocation->educationLevel?->order !== null)
            ->groupBy(fn (AccountingSubsidyAllocation $allocation): int => (int) $allocation->educationLevel->order)
            ->map(fn (Collection $items): float => round((float) $items->sum('amount'), 2))
            ->all();
        $calculableTotal = (float) collect($comparableByFamily)->sum();
        $allocatedTotal = (float) collect($byLevel)->sum();
        $fullTotal = (float) collect($byFamily)->sum();
        $periodStart = $paymentPeriod->startOfMonth();
        $periodEnd = $paymentPeriod->endOfMonth();
        $incomes = AccountingIncome::query()
            ->with('fundingSource:id,code,name')
            ->withCount('subsidyMatches')
            ->whereBetween('received_at', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->where('income_type', 'like', 'subvencion_%')
            ->where('status', '!=', 'anulado')
            ->orderBy('received_at')
            ->orderBy('id')
            ->get([
                'id', 'code', 'received_at', 'income_type', 'funding_source_id',
                'document_reference', 'amount', 'status',
            ]);
        $incomeRecords = $incomes->map(function (AccountingIncome $income): array {
            $family = $this->incomeFamily((string) $income->income_type);
            $comparisonScope = in_array($family, [...self::CALCULABLE_FAMILIES, 'sep_combined'], true);

            return [
                'id' => $income->id,
                'code' => $income->code,
                'received_at' => $income->received_at?->toDateString(),
                'income_type' => $income->income_type,
                'family' => $family,
                'family_label' => $this->familyLabel($family ?? 'sin_clasificar'),
                'comparison_scope' => $comparisonScope,
                'funding_source' => $income->fundingSource ? [
                    'code' => $income->fundingSource->code,
                    'name' => $income->fundingSource->name,
                ] : null,
                'document_reference' => $income->document_reference,
                'amount' => round((float) $income->amount, 2),
                'status' => $income->status,
                'matched_to_settlement' => (int) $income->subsidy_matches_count > 0,
            ];
        })->values();
        $incomeByFamily = $incomeRecords
            ->filter(fn (array $income): bool => in_array($income['family'], self::CALCULABLE_FAMILIES, true))
            ->groupBy('family')
            ->map(fn (Collection $items): float => round((float) $items->sum('amount'), 2))
            ->all();
        $comparableIncomeTotal = round((float) $incomeRecords
            ->where('comparison_scope', true)
            ->sum('amount'), 2);
        $unallocatedComparableIncomeTotal = round((float) $incomeRecords
            ->where('family', 'sep_combined')
            ->sum('amount'), 2);
        $incomeTotal = round((float) $incomeRecords->sum('amount'), 2);

        return [
            'settlement_count' => $settlements->count(),
            'by_family' => $byFamily,
            'comparable_by_family' => $comparableByFamily,
            'by_level' => $byLevel,
            'gross_by_level' => $grossByLevel,
            'components_by_level' => $componentsByLevel,
            'unallocated_by_family' => $unallocatedByFamily,
            'unallocated_total' => max(0, round($calculableTotal - $allocatedTotal, 2)),
            'excluded_total' => max(0, round($fullTotal - $calculableTotal, 2)),
            'income_count' => $incomeRecords->count(),
            'income_total' => $incomeTotal,
            'income_by_family' => $incomeByFamily,
            'comparable_income_total' => $comparableIncomeTotal,
            'unallocated_comparable_income_total' => $unallocatedComparableIncomeTotal,
            'excluded_income_total' => max(0, round($incomeTotal - $comparableIncomeTotal, 2)),
            'income_records' => $incomeRecords->all(),
        ];
    }

    private function incomeFamily(string $incomeType): ?string
    {
        $normalized = Str::of(Str::ascii($incomeType))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->replaceStart('subvencion_', '')
            ->toString();

        return match (true) {
            in_array($normalized, ['general', 'normal'], true) => 'normal',
            str_contains($normalized, 'prioritari') => 'sep_prioritario',
            str_contains($normalized, 'preferent') => 'sep_preferente',
            $normalized === 'sep' => 'sep_combined',
            str_contains($normalized, 'pro_ret') || str_contains($normalized, 'retencion') => 'pro_retention',
            str_contains($normalized, 'bono') => 'school_bonus',
            str_contains($normalized, 'pie') => 'pie',
            $normalized !== '' => $normalized,
            default => null,
        };
    }

    /** @param array<string,mixed> $options */
    private function isComparableActualLine(
        AccountingSubsidySettlement $settlement,
        AccountingSubsidySettlementLine $line,
        CarbonImmutable $paymentPeriod,
        array $options,
        string $concentrationBand,
    ): bool {
        if ($line->informative) {
            return false;
        }
        if ($line->concept_code === 'manual_gross') {
            return true;
        }
        if ($settlement->subsidy_type === 'sep_preferente') {
            return true;
        }
        if ($settlement->subsidy_type === 'sep_prioritario') {
            return $line->concept_code !== 'sep_concentration' || $concentrationBand !== 'none';
        }
        if ($settlement->subsidy_type !== 'normal') {
            return false;
        }

        return match ($line->concept_code) {
            'subsidy_base' => true,
            'gratuity' => $options['include_gratuity'],
            'pension_reform' => $paymentPeriod->gte(CarbonImmutable::parse(config('accounting_subsidy_estimation.pension_reform.valid_from'))),
            default => false,
        };
    }

    private function familyLabel(string $family): string
    {
        return match ($family) {
            'normal' => 'Subvención General',
            'sep_prioritario' => 'SEP Prioritaria + concentración',
            'sep_preferente' => 'SEP Preferente',
            'sep_combined' => 'SEP sin desglose',
            'pro_retention' => 'Pro-Retención',
            'school_bonus' => 'Bono Escolar',
            'cd_brp' => 'CD-BRP',
            'cd_asignacion_tramo' => 'CD-Asignación por tramo',
            'pie' => 'Programa de Integración Escolar',
            'sin_clasificar' => 'Sin clasificación',
            default => Str::headline($family),
        };
    }

    /**
     * Rebuilds the comparable liquidation at 100% attendance. Article 13 and
     * SEP apply the unit value linearly to the corresponding average
     * attendance, so each official component can be divided by the accumulated
     * attendance rate of its population.
     *
     * @param  array<string,array<string,float|int|string>>  $components
     * @return array{by_family:array<string,float>,unprojectable_families:array<int,string>}
     */
    private function projectFullAttendanceFromLiquidation(
        array $components,
        ?float $generalRate,
        ?float $priorityRate,
        ?float $preferentialRate,
    ): array {
        $projected = [];
        $unprojectable = [];

        foreach ($components as $family => $concepts) {
            foreach ($concepts as $concept => $amount) {
                $amount = (float) $amount;
                if (abs($amount) <= 0.01) {
                    continue;
                }

                $rate = match ($family) {
                    'normal' => $generalRate,
                    'sep_preferente' => $preferentialRate,
                    'sep_prioritario' => $concept === 'sep_concentration' ? $generalRate : $priorityRate,
                    default => null,
                };

                if ($rate === null || $rate <= 0) {
                    $unprojectable[] = $family;

                    continue;
                }

                $projected[$family] = ($projected[$family] ?? 0) + ($amount / min(1, $rate));
            }
        }

        return [
            'by_family' => collect($projected)->map(fn (float $amount): float => round($amount, 2))->all(),
            'unprojectable_families' => collect($unprojectable)->unique()->values()->all(),
        ];
    }

    private function lossAgainstFullAttendance(float $fullAttendance, float $actual): float
    {
        $loss = max(0, round($fullAttendance - $actual, 2));

        return $loss <= max(1, abs($fullAttendance) * 0.01) ? 0.0 : $loss;
    }

    private function warnings(Collection $missingPeriods, Collection $monthly, array $options, string $concentrationBasis, array $actual): array
    {
        $warnings = [];
        if ($missingPeriods->isNotEmpty()) {
            $warnings[] = 'Faltan cargas de asistencia para: '.$missingPeriods->implode(', ').'. El cálculo usa sólo los meses disponibles.';
        }
        $unmapped = (int) $monthly->sum('unmapped_rows');
        if ($unmapped > 0) {
            $warnings[] = "{$unmapped} filas de asistencia no pudieron asignarse a un nivel y fueron excluidas.";
        }
        $invalid = (int) $monthly->sum('invalid_attendance_rows');
        if ($invalid > 0) {
            $warnings[] = "{$invalid} filas sin días de clase válidos fueron excluidas.";
        }
        if ($options['concentration_band'] === 'auto') {
            $warnings[] = 'La concentración SEP está '.$concentrationBasis.'. Confirma la banda oficial antes de cerrar el informe.';
        }
        if ((float) $actual['unallocated_total'] > 0) {
            $warnings[] = 'Parte de la liquidación calculable no tiene distribución por nivel en los anexos importados.';
        }
        if ((float) $actual['excluded_total'] > 0) {
            $warnings[] = 'Las glosas no derivables sólo desde asistencia se separaron del monto liquidado comparable.';
        }
        if ((int) $actual['income_count'] === 0) {
            $warnings[] = 'No hay ingresos de subvención registrados en el módulo de Ingresos para el mes de pago seleccionado.';
        }
        if ((float) $actual['unallocated_comparable_income_total'] > 0) {
            $warnings[] = 'Existen ingresos registrados como SEP sin desglose entre prioridad y preferencia; se comparan sólo en el total y no se distribuyen por familia.';
        }
        if ((float) $actual['excluded_income_total'] > 0) {
            $warnings[] = 'Los ingresos de subvenciones no calculables sólo con asistencia se excluyeron de la diferencia contra el esperado.';
        }
        $warnings[] = 'PIE no se estima desde una marca booleana: requiere tipo de NEE, cupos y anexos oficiales.';
        $warnings[] = 'Zona, ruralidad, reliquidaciones, topes y otros ajustes oficiales no están incluidos en el esperado base.';

        return $warnings;
    }

    private function emptyResult(
        CarbonImmutable $paymentPeriod,
        array $options,
        ?array $schedule,
        array $requiredPeriods,
        Collection $foundPeriods,
        Collection $missingPeriods,
        array $actual,
    ): array {
        return [
            'available' => false,
            'status' => $schedule ? 'sin_asistencia' : 'sin_parametros',
            'payment_period' => $paymentPeriod->format('Y-m'),
            'metrics' => [
                'full_attendance_total' => 0,
                'attendance_loss_total' => 0,
                'attendance_loss_percentage' => null,
                'expected_total' => 0,
                'liquidated_gross_total' => round((float) collect($actual['by_family'])->sum(), 2),
                'liquidated_total' => round((float) collect($actual['comparable_by_family'])->sum(), 2),
                'excluded_liquidated_total' => round((float) $actual['excluded_total'], 2),
                'difference_total' => null,
                'registered_income_total' => round((float) $actual['income_total'], 2),
                'registered_comparable_income_total' => round((float) $actual['comparable_income_total'], 2),
                'registered_income_unallocated_total' => round((float) $actual['unallocated_comparable_income_total'], 2),
                'excluded_registered_income_total' => round((float) $actual['excluded_income_total'], 2),
                'income_difference_total' => null,
                'income_difference_percentage' => null,
                'settlement_count' => (int) $actual['settlement_count'],
                'income_records_count' => (int) $actual['income_count'],
                'income_status' => (int) $actual['income_count'] > 0 ? 'sin_base' : 'sin_ingreso',
                'coverage_percentage' => count($requiredPeriods) > 0 ? round(($foundPeriods->count() / count($requiredPeriods)) * 100, 2) : 0,
            ],
            'window' => [
                'required_periods' => $requiredPeriods,
                'found_periods' => $foundPeriods->all(),
                'missing_periods' => $missingPeriods->all(),
                'source_imports' => [],
            ],
            'assumptions' => [
                ...$options,
                'use_value' => $schedule['use_value'] ?? null,
                'rate_label' => $schedule['label'] ?? null,
                'rate_valid_from' => $schedule['valid_from'] ?? null,
            ],
            'by_level' => [],
            'by_subsidy' => collect($actual['by_family'])->map(function ($amount, $family) use ($actual): array {
                $calculable = in_array($family, self::CALCULABLE_FAMILIES, true);

                return [
                    'key' => $family,
                    'label' => $this->familyLabel($family),
                    'calculable_from_attendance' => $calculable,
                    'expected_amount' => null,
                    'liquidated_amount' => round((float) ($calculable ? ($actual['comparable_by_family'][$family] ?? 0) : $amount), 2),
                    'liquidated_total_amount' => round((float) $amount, 2),
                    'difference_amount' => null,
                    'income_amount' => round((float) ($actual['income_by_family'][$family] ?? 0), 2),
                    'income_difference_amount' => null,
                ];
            })->values()->all(),
            'income_records' => $actual['income_records'],
            'warnings' => [
                $schedule
                    ? 'No existen cargas de asistencia para la ventana regulatoria requerida.'
                    : 'No existe una tabla oficial parametrizada para este período.',
                'PIE y otras glosas que requieren antecedentes adicionales no se estiman desde asistencia.',
            ],
            'sources' => config('accounting_subsidy_estimation.sources', []),
            'disclaimer' => 'Estimación de control interno. La merma por inasistencia compara con un escenario teórico de 100% de asistencia y no representa por sí sola un monto exigible. La liquidación oficial MINEDUC prevalece.',
        ];
    }
}
