<?php

namespace App\Services\Remuneration;

use App\Models\Remuneration\RemunerationConcept;
use App\Models\Remuneration\RemunerationContractSetting;
use App\Models\Remuneration\RemunerationEmployeeConcept;
use App\Models\Remuneration\RemunerationEmployeeProfile;
use App\Models\Remuneration\RemunerationMovement;
use App\Models\Remuneration\RemunerationPayroll;
use App\Models\Remuneration\RemunerationPayrollDistribution;
use App\Models\Remuneration\RemunerationPayrollLine;
use App\Models\Remuneration\RemunerationPeriod;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollCalculationService
{
    public const CALCULATION_VERSION = 'v1';

    public function __construct(
        private readonly RemunerationParameterResolver $parameterResolver,
        private readonly SafeFormulaEvaluator $formulaEvaluator,
        private readonly RemunerationRoundingService $roundingService,
        private readonly RemunerationAuditService $auditService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function calculate(RemunerationPeriod $period, Staff $staff, ?User $user, array $options = []): RemunerationPayroll
    {
        if ($period->isClosed()) {
            throw ValidationException::withMessages([
                'period_id' => 'No se puede recalcular una liquidación de un período cerrado.',
            ]);
        }

        return DB::transaction(function () use ($period, $staff, $user, $options) {
            $profile = RemunerationEmployeeProfile::query()
                ->where('staff_id', $staff->id)
                ->where('is_active', true)
                ->first();

            if (!$profile) {
                throw ValidationException::withMessages([
                    'staff_id' => 'El funcionario no tiene ficha previsional de remuneraciones activa.',
                ]);
            }

            $contractSetting = $this->resolveContractSetting($period, $staff, $options['contract_id'] ?? null);
            $parameters = $this->parameterResolver->resolveForPeriod($period);
            $standardMonthDays = $this->parameterResolver->requiredValue($parameters, 'standard_month_days');
            if ($standardMonthDays <= 0) {
                throw ValidationException::withMessages([
                    'parameters' => 'El parámetro standard_month_days debe ser mayor que cero.',
                ]);
            }

            $payrollType = (string) ($options['payroll_type'] ?? 'mensual');
            $payroll = RemunerationPayroll::query()
                ->where('period_id', $period->id)
                ->where('staff_id', $staff->id)
                ->where('payroll_type', $payrollType)
                ->first();

            if ($payroll && $payroll->isLocked()) {
                throw ValidationException::withMessages([
                    'payroll' => 'La liquidación ya está aprobada, pagada o anulada. Use una complementaria o reliquidación.',
                ]);
            }

            $approvedMovements = $this->approvedMovements($period, $staff, $contractSetting);
            $workedDays = max(0, $standardMonthDays - (float) $approvedMovements->sum('affects_days'));
            $variables = array_merge($this->parameterResolver->variables($parameters), [
                'base_salary' => (int) $contractSetting->base_salary,
                'contract_hours' => (float) $contractSetting->weekly_hours,
                'basic_hours' => (float) ($contractSetting->basic_hours ?? 0),
                'middle_hours' => (float) ($contractSetting->middle_hours ?? 0),
                'pie_hours' => (float) ($contractSetting->pie_hours ?? 0),
                'sep_hours' => (float) ($contractSetting->sep_hours ?? 0),
                'pro_retention_hours' => (float) ($contractSetting->pro_retention_hours ?? 0),
                'teacher_career' => $contractSetting->teacher_career ? 1 : 0,
                'priority_percent' => (float) ($contractSetting->priority_percent ?? 0),
                'period_days' => $standardMonthDays,
                'standard_month_days' => $standardMonthDays,
                'worked_days' => $workedDays,
            ]);

            $lines = [];
            foreach ($this->systemConcepts() as $concept) {
                $line = $this->lineFromConcept($concept, $variables, 'concepto_sistema');
                if ($line['amount'] !== 0) {
                    $lines[] = $line;
                }
            }

            foreach ($this->employeeConcepts($period, $staff, $contractSetting) as $employeeConcept) {
                $concept = $employeeConcept->concept;
                $line = $this->lineFromConcept(
                    $concept,
                    $variables,
                    'concepto_trabajador',
                    $employeeConcept->amount,
                    $employeeConcept->formula_override
                );
                if ($line['amount'] !== 0) {
                    $lines[] = $line;
                }
            }

            foreach ($approvedMovements as $movement) {
                $lines[] = $this->lineFromMovement($movement);
            }

            $grossTaxable = $this->sumLines($lines, fn ($line) => $line['line_type'] === 'earning' && $line['is_imponible']);
            $grossNonTaxable = $this->sumLines($lines, fn ($line) => $line['line_type'] === 'earning' && !$line['is_imponible']);
            $grossTotal = $grossTaxable + $grossNonTaxable;
            $taxableAmount = $this->sumLines($lines, fn ($line) => $line['line_type'] === 'earning' && $line['affects_tax_base']);
            $otherDeductions = $this->sumLines($lines, fn ($line) => $line['line_type'] === 'deduction');

            $legalDeductionLines = $this->legalDeductionLines($profile, $parameters, $grossTaxable, $taxableAmount);
            $employerContributionLines = $this->employerContributionLines($parameters, $grossTaxable);

            $lines = array_merge($lines, $legalDeductionLines, $employerContributionLines);

            $legalDeductions = $this->sumLines($legalDeductionLines, fn () => true);
            $employerContributions = $this->sumLines($employerContributionLines, fn () => true);
            $totalDeductions = $legalDeductions + $otherDeductions;
            $netAmount = max(0, $grossTotal - $totalDeductions);
            $totalCost = $grossTotal + $employerContributions;

            $payroll ??= new RemunerationPayroll();
            $oldValues = $payroll->exists ? $payroll->getOriginal() : [];
            $code = sprintf('REM-%04d%02d-%s-%d', $period->year, $period->month, strtoupper(substr($payrollType, 0, 3)), $staff->id);

            $payroll->fill([
                'period_id' => $period->id,
                'staff_id' => $staff->id,
                'contract_id' => $contractSetting->contract_id,
                'employee_profile_id' => $profile->id,
                'code' => $code,
                'payroll_type' => $payrollType,
                'status' => 'calculada',
                'calculation_version' => self::CALCULATION_VERSION,
                'calculated_at' => now(),
                'calculated_by' => $user?->id,
                'gross_taxable_amount' => $grossTaxable,
                'gross_non_taxable_amount' => $grossNonTaxable,
                'gross_total' => $grossTotal,
                'taxable_amount' => $taxableAmount,
                'legal_deductions' => $legalDeductions,
                'other_deductions' => $otherDeductions,
                'total_deductions' => $totalDeductions,
                'employer_contributions' => $employerContributions,
                'net_amount' => $netAmount,
                'total_cost' => $totalCost,
                'snapshot' => [
                    'parameters' => $this->parameterResolver->snapshot($parameters),
                    'variables' => $variables,
                    'period' => $period->only(['id', 'year', 'month', 'name', 'period_start', 'period_end', 'status']),
                    'staff' => $staff->only(['id', 'full_name', 'rut', 'birth_date', 'start_date', 'contract_type', 'contract_hours', 'cargo_id']),
                    'contract_setting' => $contractSetting->toArray(),
                    'employee_profile' => $profile->toArray(),
                    'movements' => $approvedMovements->pluck('id')->all(),
                    'calculated_by' => $user?->id,
                    'calculated_at' => now()->toIso8601String(),
                    'calculation_version' => self::CALCULATION_VERSION,
                ],
                'updated_by' => $user?->id,
            ]);

            if (!$payroll->exists) {
                $payroll->created_by = $user?->id;
            }

            $payroll->save();
            $payroll->lines()->delete();
            $payroll->distributions()->delete();

            foreach (array_values($lines) as $index => $line) {
                RemunerationPayrollLine::query()->create(array_merge($line, [
                    'payroll_id' => $payroll->id,
                    'sort_order' => ($index + 1) * 10,
                ]));
            }

            $this->storeDistributions($payroll, $contractSetting);

            $fresh = $payroll->fresh(['period', 'staff.cargo', 'contract', 'employeeProfile', 'lines', 'distributions.fundingSource', 'distributions.costCenter']);
            $this->auditService->log(
                $oldValues === [] ? 'calcular' : 'recalcular',
                $fresh,
                $user,
                $oldValues,
                $fresh?->getAttributes() ?? [],
                'Cálculo de liquidación de sueldo.',
                null,
                ['period_id' => $period->id, 'staff_id' => $staff->id]
            );

            return $fresh;
        });
    }

    private function resolveContractSetting(RemunerationPeriod $period, Staff $staff, mixed $contractId): RemunerationContractSetting
    {
        $query = RemunerationContractSetting::query()
            ->with(['staff', 'contract'])
            ->where('staff_id', $staff->id)
            ->where('is_active', true)
            ->where(function ($builder) use ($period) {
                $builder->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $period->period_end);
            })
            ->where(function ($builder) use ($period) {
                $builder->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $period->period_start);
            });

        if ($contractId) {
            $query->where('contract_id', $contractId);
        }

        $setting = $query->orderByDesc('effective_from')->orderByDesc('id')->first();

        if (!$setting) {
            throw ValidationException::withMessages([
                'contract_id' => 'No existe configuración remuneracional activa para el funcionario y período.',
            ]);
        }

        return $setting;
    }

    /**
     * @return Collection<int, RemunerationConcept>
     */
    private function systemConcepts(): Collection
    {
        return RemunerationConcept::query()
            ->where('is_active', true)
            ->where('is_system', true)
            ->where('is_legal', false)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, RemunerationEmployeeConcept>
     */
    private function employeeConcepts(RemunerationPeriod $period, Staff $staff, RemunerationContractSetting $setting): Collection
    {
        return RemunerationEmployeeConcept::query()
            ->with('concept')
            ->where('staff_id', $staff->id)
            ->where('is_active', true)
            ->where(function ($query) use ($setting) {
                $query->whereNull('contract_id');
                if ($setting->contract_id) {
                    $query->orWhere('contract_id', $setting->contract_id);
                }
            })
            ->where(function ($query) use ($period) {
                $query->whereNull('starts_at')
                    ->orWhereDate('starts_at', '<=', $period->period_end);
            })
            ->where(function ($query) use ($period) {
                $query->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', $period->period_start);
            })
            ->get();
    }

    /**
     * @return Collection<int, RemunerationMovement>
     */
    private function approvedMovements(RemunerationPeriod $period, Staff $staff, RemunerationContractSetting $setting): Collection
    {
        return RemunerationMovement::query()
            ->with('concept')
            ->where('period_id', $period->id)
            ->where('staff_id', $staff->id)
            ->whereIn('status', ['aprobado', 'ejecutado'])
            ->where(function ($query) use ($setting) {
                $query->whereNull('contract_id');
                if ($setting->contract_id) {
                    $query->orWhere('contract_id', $setting->contract_id);
                }
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, float|int>  $variables
     * @return array<string, mixed>
     */
    private function lineFromConcept(
        RemunerationConcept $concept,
        array $variables,
        string $source,
        int|string|null $amountOverride = null,
        ?string $formulaOverride = null,
    ): array {
        $formula = $formulaOverride ?: $concept->formula;
        $amount = match ($concept->calculation_type) {
            'formula' => $this->roundingService->clp($this->formulaEvaluator->evaluate($formula, $variables)),
            'fixed' => $this->roundingService->clp($amountOverride ?? $concept->amount ?? 0),
            default => $this->roundingService->clp($amountOverride ?? $concept->amount ?? 0),
        };

        return [
            'concept_id' => $concept->id,
            'line_type' => $concept->type,
            'code' => $concept->code,
            'name' => $concept->name,
            'is_taxable' => $concept->is_taxable,
            'is_imponible' => $concept->is_imponible,
            'affects_tax_base' => $concept->affects_tax_base,
            'affects_net' => $concept->affects_net,
            'amount' => $amount,
            'formula' => $formula,
            'source' => $source,
            'snapshot' => [
                'concept' => $concept->toArray(),
                'variables' => $variables,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lineFromMovement(RemunerationMovement $movement): array
    {
        $concept = $movement->concept;
        $lineType = $concept?->type ?: (in_array($movement->movement_type, ['deduction', 'descuento', 'delay', 'atraso', 'absence', 'ausencia'], true) ? 'deduction' : 'earning');

        return [
            'concept_id' => $concept?->id,
            'source_movement_id' => $movement->id,
            'line_type' => $lineType,
            'code' => $concept?->code ?: 'movimiento_' . $movement->id,
            'name' => $movement->description,
            'is_taxable' => (bool) ($concept?->is_taxable ?? false),
            'is_imponible' => (bool) ($concept?->is_imponible ?? false),
            'affects_tax_base' => (bool) ($concept?->affects_tax_base ?? false),
            'affects_net' => (bool) ($concept?->affects_net ?? true),
            'amount' => abs((int) $movement->amount),
            'quantity' => $movement->quantity,
            'unit_value' => $movement->unit_value,
            'source' => $movement->movement_type,
            'snapshot' => [
                'movement' => $movement->toArray(),
                'concept' => $concept?->toArray(),
            ],
        ];
    }

    /**
     * @param  Collection<string, mixed>  $parameters
     * @return array<int, array<string, mixed>>
     */
    private function legalDeductionLines(RemunerationEmployeeProfile $profile, Collection $parameters, int $grossTaxable, int $taxableAmount): array
    {
        $afpRate = $profile->afp_rate !== null
            ? $this->parameterResolver->normalizeRate((float) $profile->afp_rate)
            : $this->parameterResolver->requiredRate($parameters, 'afp_rate_default');

        $healthRate = $this->parameterResolver->requiredRate($parameters, 'health_rate_default');
        $afcWorkerRate = $profile->has_afc ? $this->parameterResolver->optionalRate($parameters, 'afc_worker_rate') : 0;
        $singleTaxRate = $this->parameterResolver->optionalRate($parameters, 'single_tax_rate');

        $healthDeduction = $this->roundingService->clp($grossTaxable * $healthRate);
        $healthPlan = $this->convertUnitAmount($profile->health_plan_amount, $profile->health_plan_unit, $parameters);
        if ($healthPlan > 0) {
            $healthDeduction = max($healthDeduction, $healthPlan);
        }

        $lines = [
            $this->legalLine('afp', 'AFP ' . ($profile->afp_name ?: 'trabajador'), $this->roundingService->clp($grossTaxable * $afpRate), 'afp_rate', $afpRate),
            $this->legalLine('salud', 'Salud ' . ($profile->health_institution_name ?: 'trabajador'), $healthDeduction, 'health_rate', $healthRate),
            $this->legalLine('afc_trabajador', 'AFC trabajador', $this->roundingService->clp($grossTaxable * $afcWorkerRate), 'afc_worker_rate', $afcWorkerRate),
            $this->legalLine('impuesto_unico', 'Impuesto único', $this->roundingService->clp($taxableAmount * $singleTaxRate), 'single_tax_rate', $singleTaxRate),
        ];

        $apv = $this->convertUnitAmount($profile->apv_amount, $profile->apv_unit, $parameters);
        if ($apv > 0) {
            $lines[] = $this->legalLine('apv', 'APV trabajador', $apv, 'apv_amount', null);
        }

        return array_values(array_filter($lines, fn ($line) => $line['amount'] > 0));
    }

    /**
     * @param  Collection<string, mixed>  $parameters
     * @return array<int, array<string, mixed>>
     */
    private function employerContributionLines(Collection $parameters, int $grossTaxable): array
    {
        $rates = [
            'sis_rate' => 'SIS empleador',
            'afc_employer_rate' => 'AFC empleador',
            'mutual_rate' => 'Mutualidad',
            'sanna_rate' => 'SANNA',
        ];

        $lines = [];
        foreach ($rates as $code => $name) {
            $rate = $this->parameterResolver->optionalRate($parameters, $code);
            $amount = $this->roundingService->clp($grossTaxable * $rate);
            if ($amount <= 0) {
                continue;
            }
            $lines[] = [
                'concept_id' => null,
                'line_type' => 'employer_contribution',
                'code' => $code,
                'name' => $name,
                'is_taxable' => false,
                'is_imponible' => false,
                'affects_tax_base' => false,
                'affects_net' => false,
                'amount' => $amount,
                'source' => 'aporte_empleador',
                'snapshot' => ['rate' => $rate, 'base' => $grossTaxable],
            ];
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    private function legalLine(string $code, string $name, int $amount, string $rateKey, ?float $rate): array
    {
        return [
            'concept_id' => null,
            'line_type' => 'deduction',
            'code' => $code,
            'name' => $name,
            'is_taxable' => false,
            'is_imponible' => false,
            'affects_tax_base' => false,
            'affects_net' => true,
            'amount' => $amount,
            'source' => 'deduccion_legal',
            'snapshot' => [$rateKey => $rate],
        ];
    }

    /**
     * @param  Collection<string, mixed>  $parameters
     */
    private function convertUnitAmount(float|string|null $amount, ?string $unit, Collection $parameters): int
    {
        $amount = (float) ($amount ?? 0);
        if ($amount <= 0) {
            return 0;
        }

        return match ($unit) {
            'uf' => $this->roundingService->clp($amount * $this->parameterResolver->requiredValue($parameters, 'uf_value')),
            'utm' => $this->roundingService->clp($amount * $this->parameterResolver->requiredValue($parameters, 'utm_value')),
            'percent' => $this->roundingService->clp($amount),
            default => $this->roundingService->clp($amount),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function sumLines(array $lines, callable $filter): int
    {
        return array_reduce($lines, function (int $carry, array $line) use ($filter) {
            return $filter($line) ? $carry + (int) $line['amount'] : $carry;
        }, 0);
    }

    private function storeDistributions(RemunerationPayroll $payroll, RemunerationContractSetting $setting): void
    {
        $distribution = $setting->funding_distribution;
        if (!is_array($distribution) || $distribution === []) {
            $distribution = [['percentage' => 100, 'funding_source_id' => null, 'cost_center_id' => null]];
        }

        $percentages = array_map(fn ($row) => (float) ($row['percentage'] ?? 0), $distribution);
        $grossParts = $this->roundingService->distribute((int) $payroll->gross_total, $percentages);
        $employerParts = $this->roundingService->distribute((int) $payroll->employer_contributions, $percentages);
        $deductionParts = $this->roundingService->distribute((int) $payroll->total_deductions, $percentages);
        $netParts = $this->roundingService->distribute((int) $payroll->net_amount, $percentages);
        $costParts = $this->roundingService->distribute((int) $payroll->total_cost, $percentages);

        foreach (array_values($distribution) as $index => $row) {
            RemunerationPayrollDistribution::query()->create([
                'payroll_id' => $payroll->id,
                'funding_source_id' => $row['funding_source_id'] ?? null,
                'cost_center_id' => $row['cost_center_id'] ?? null,
                'percentage' => (float) ($row['percentage'] ?? 0),
                'gross_amount' => $grossParts[$index] ?? 0,
                'employer_contribution_amount' => $employerParts[$index] ?? 0,
                'deduction_amount' => $deductionParts[$index] ?? 0,
                'net_amount' => $netParts[$index] ?? 0,
                'total_cost_amount' => $costParts[$index] ?? 0,
                'snapshot' => $row,
            ]);
        }
    }
}
