<?php

namespace App\Services\Remuneration\Payslips;

class PayslipDistributionService
{
    /**
     * @param  array<string,mixed>  $parsed
     * @return array{summaries:array<int,array<string,mixed>>,discount_allocations:array<int,array<string,mixed>>,issues:array<int,array<string,string>>,controls:array<int,array<string,mixed>>}
     */
    public function distribute(array $parsed): array
    {
        $issues = [];
        $sources = collect($parsed['funding_labels'] ?? [])->values();
        $earnings = collect($parsed['earnings'] ?? []);
        $discounts = collect($parsed['discounts'] ?? []);
        $contributions = collect($parsed['employer_contributions'] ?? []);

        $bases = $sources->mapWithKeys(function (string $source) use ($earnings): array {
            $rows = $earnings->where('funding_label', $source);

            return [$source => [
                'taxable' => (int) $rows->where('is_imponible', true)->sum('amount'),
                'non_taxable' => (int) $rows->where('is_imponible', false)->sum('amount'),
                'gross' => (int) $rows->sum('amount'),
            ]];
        });

        $totalTaxable = (int) $bases->sum('taxable');
        $totalGross = (int) $bases->sum('gross');
        if ($totalTaxable <= 0 && $discounts->whereIn('classification', ['legal_previsional', 'impuesto_unico'])->sum('amount') > 0) {
            $issues[] = ['code' => 'invalid_taxable_base', 'severity' => 'error', 'message' => 'La base imponible es cero o negativa para descuentos legales.'];
        }
        if ($totalGross <= 0 && $discounts->whereNotIn('classification', ['legal_previsional', 'impuesto_unico'])->sum('amount') > 0) {
            $issues[] = ['code' => 'invalid_gross_base', 'severity' => 'error', 'message' => 'La base de haberes es cero o negativa para otros descuentos.'];
        }

        $allocations = [];
        foreach ($discounts->values() as $discountIndex => $discount) {
            if (in_array($discount['classification'], ['non_distributable', 'pending'], true)) {
                $issues[] = ['code' => 'unclassified_discount', 'severity' => 'error', 'message' => 'Existe un descuento no distribuible o pendiente de clasificación.'];

                continue;
            }
            $legal = in_array($discount['classification'], ['legal_previsional', 'impuesto_unico'], true);
            $baseKey = $legal ? 'taxable' : 'gross';
            $baseTotal = $legal ? $totalTaxable : $totalGross;
            if ($baseTotal <= 0) {
                continue;
            }
            $assigned = 0;
            foreach ($sources as $sourceIndex => $source) {
                $base = (int) $bases[$source][$baseKey];
                $proportion = $base / $baseTotal;
                $calculated = ((int) $discount['amount']) * $proportion;
                $rounded = (int) round($calculated, 0, PHP_ROUND_HALF_UP);
                $final = $sourceIndex === $sources->count() - 1
                    ? (int) $discount['amount'] - $assigned
                    : $rounded;
                $assigned += $final;
                $allocations[] = [
                    'discount_index' => $discountIndex,
                    'funding_label' => $source,
                    'base_amount' => $base,
                    'proportion' => $proportion,
                    'calculated_amount' => $calculated,
                    'rounding_adjustment' => $final - $rounded,
                    'assigned_amount' => $final,
                ];
            }
        }

        $allocationCollection = collect($allocations);
        $summaries = $sources->map(function (string $source) use ($bases, $allocationCollection, $discounts, $contributions): array {
            $sourceAllocations = $allocationCollection->where('funding_label', $source);
            $legal = (int) $sourceAllocations->filter(fn (array $allocation): bool => in_array($discounts[$allocation['discount_index']]['classification'], ['legal_previsional', 'impuesto_unico'], true))->sum('assigned_amount');
            $other = (int) $sourceAllocations->reject(fn (array $allocation): bool => in_array($discounts[$allocation['discount_index']]['classification'], ['legal_previsional', 'impuesto_unico'], true))->sum('assigned_amount');
            $employer = (int) $contributions->where('funding_label', $source)->sum('amount');
            $gross = (int) $bases[$source]['gross'];
            $net = $gross - $legal - $other;

            return [
                'funding_label' => $source,
                'taxable_earnings' => (int) $bases[$source]['taxable'],
                'non_taxable_earnings' => (int) $bases[$source]['non_taxable'],
                'gross_earnings' => $gross,
                'legal_deductions' => $legal,
                'other_deductions' => $other,
                'net_amount' => $net,
                'employer_contributions' => $employer,
                'total_cost' => $gross + $employer,
            ];
        })->values()->all();

        $totals = $parsed['totals'] ?? [];
        $controls = [
            $this->control('gross_detail', 'Detalle de haberes = total haberes', (int) ($totals['gross_total'] ?? 0), (int) $earnings->sum('amount')),
            $this->control('gross_composition', 'Imponibles + no imponibles = total haberes', (int) ($totals['gross_total'] ?? 0), (int) ($totals['gross_taxable'] ?? 0) + (int) ($totals['gross_non_taxable'] ?? 0)),
            $this->control('deduction_detail', 'Detalle descuentos = total descuentos', (int) ($totals['total_deductions'] ?? 0), (int) $discounts->sum('amount')),
            $this->control('net_equation', 'Haberes - descuentos = líquido', (int) ($totals['net_amount'] ?? 0), (int) ($totals['gross_total'] ?? 0) - (int) ($totals['total_deductions'] ?? 0)),
            $this->control('distributed_discounts', 'Descuentos distribuidos = total descuentos', (int) ($totals['total_deductions'] ?? 0), (int) $allocationCollection->sum('assigned_amount')),
            $this->control('distributed_net', 'Líquidos por subvención = líquido', (int) ($totals['net_amount'] ?? 0), (int) collect($summaries)->sum('net_amount')),
            $this->control('employer_contributions', 'Detalle aportes = total aportes', (int) ($totals['employer_contributions'] ?? 0), (int) $contributions->sum('amount')),
        ];

        $printed = $parsed['printed_funding_totals'] ?? [];
        foreach ($sources as $source) {
            $safeCode = mb_substr(preg_replace('/[^A-Z0-9]+/', '_', $source) ?: 'SOURCE', 0, 60);
            if (array_key_exists($source, $printed['taxable'] ?? [])) {
                $controls[] = $this->control(
                    'printed_taxable_'.$safeCode,
                    'Detalle imponible = total impreso de '.$source,
                    (int) $printed['taxable'][$source],
                    (int) $bases[$source]['taxable'],
                );
            }
            if (array_key_exists($source, $printed['non_taxable'] ?? [])) {
                $controls[] = $this->control(
                    'printed_non_taxable_'.$safeCode,
                    'Detalle no imponible = total impreso de '.$source,
                    (int) $printed['non_taxable'][$source],
                    (int) $bases[$source]['non_taxable'],
                );
            }
            if (array_key_exists($source, $printed['employer_contributions'] ?? [])) {
                $controls[] = $this->control(
                    'printed_employer_'.$safeCode,
                    'Detalle de aportes = total impreso de '.$source,
                    (int) $printed['employer_contributions'][$source],
                    (int) $contributions->where('funding_label', $source)->sum('amount'),
                );
            }
        }

        return compact('summaries', 'allocations', 'issues', 'controls') + ['discount_allocations' => $allocations];
    }

    private function control(string $code, string $label, int $expected, int $actual): array
    {
        $difference = $actual - $expected;

        return [
            'code' => $code,
            'label' => $label,
            'status' => $difference === 0 ? 'correcto' : 'error',
            'expected_amount' => $expected,
            'actual_amount' => $actual,
            'difference' => $difference,
        ];
    }
}
