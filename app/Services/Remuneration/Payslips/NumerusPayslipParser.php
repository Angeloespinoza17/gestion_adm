<?php

namespace App\Services\Remuneration\Payslips;

use App\Services\Remuneration\Payslips\Contracts\PayslipParserInterface;

class NumerusPayslipParser implements PayslipParserInterface
{
    private const MONTHS = [
        'ENERO' => 1, 'FEBRERO' => 2, 'MARZO' => 3, 'ABRIL' => 4,
        'MAYO' => 5, 'JUNIO' => 6, 'JULIO' => 7, 'AGOSTO' => 8,
        'SEPTIEMBRE' => 9, 'SETIEMBRE' => 9, 'OCTUBRE' => 10,
        'NOVIEMBRE' => 11, 'DICIEMBRE' => 12,
    ];

    public function __construct(private readonly PayslipNormalizer $normalizer) {}

    public function provider(): string
    {
        return 'numerus';
    }

    public function version(): string
    {
        return (string) config('remuneration_payslips.parser_version', 'numerus-v1.0.0');
    }

    public function confidence(array $page): float
    {
        $normalized = $this->normalizer->label($page['text'] ?? '');
        $score = 0.0;
        $score += str_contains($normalized, 'LIQUIDACION DE SUELDO') ? 0.45 : 0;
        $score += str_contains($normalized, 'ALCANCE LIQUIDO') ? 0.2 : 0;
        $score += str_contains($normalized, 'APORTE EMPLEADOR LEYES SOCIALES') ? 0.2 : 0;
        $score += str_contains(mb_strtolower((string) ($page['text'] ?? '')), 'numerus.cl') ? 0.15 : 0;

        return min(1, $score);
    }

    public function parse(array $page): array
    {
        $tokens = collect($page['tokens'] ?? [])->sortByDesc('y')->values()->all();
        $warnings = [];
        if ($tokens === []) {
            $warnings[] = ['code' => 'ocr_without_layout', 'message' => 'El OCR no entregó coordenadas; se requiere revisión manual de subvenciones.', 'severity' => 'error'];
        }

        [$year, $month] = $this->period((string) ($page['text'] ?? ''));
        $rut = $this->valueBelow($tokens, 'RUT TRABAJADOR');
        $rutNormalized = $this->normalizer->rut($rut);
        if (! $this->normalizer->validRut($rutNormalized)) {
            $warnings[] = ['code' => 'invalid_rut', 'message' => 'El RUT de trabajador no pudo validarse.', 'severity' => 'error'];
        }

        $earningsHeader = $this->token($tokens, 'HABERES');
        $discountsHeader = $this->token($tokens, 'DESCUENTOS');
        $netHeader = $this->token($tokens, 'ALCANCE LIQUIDO');
        $contributionHeader = $this->tokenStartsWith($tokens, 'APORTE EMPLEADOR LEYES SOCIALES');

        $earningSources = $earningsHeader ? $this->sourcesAtY($tokens, (float) $earningsHeader['y']) : [];
        $contributionSources = $contributionHeader ? $this->contributionSources($tokens, $contributionHeader) : [];
        $fundingLabels = collect([...$earningSources, ...$contributionSources])->pluck('label')->unique()->values()->all();

        $earnings = ($earningsHeader && $discountsHeader)
            ? $this->parseEarnings($tokens, $earningsHeader, $discountsHeader, $earningSources)
            : [];
        $discounts = ($discountsHeader && $netHeader)
            ? $this->parseDiscounts($tokens, $discountsHeader, $netHeader)
            : [];
        $contributions = $contributionHeader
            ? $this->parseContributions($tokens, $contributionHeader, $contributionSources)
            : [];

        $totals = [
            'gross_taxable' => $this->totalAt($tokens, 'TOTAL IMPONIBLE', 500),
            'gross_non_taxable' => $this->totalAt($tokens, 'TOTAL NO IMPONIBLE', 500),
            'gross_total' => $this->totalAt($tokens, 'TOTAL HABERES', 500),
            'legal_deductions' => $this->totalAt($tokens, 'TOTAL DESC LEGALES', 500),
            'other_deductions' => $this->totalAt($tokens, 'TOTAL DESC OTROS', 500),
            'total_deductions' => $this->totalAt($tokens, 'TOTAL DESCUENTOS', 500),
            'net_amount' => $netHeader ? $this->moneyRightOf($tokens, (float) $netHeader['y'], 500) : 0,
            'employer_contributions' => $this->contributionTotal($tokens, $contributionHeader),
        ];

        if ($totals['gross_total'] !== $totals['gross_taxable'] + $totals['gross_non_taxable']) {
            $warnings[] = ['code' => 'gross_total_mismatch', 'message' => 'Total haberes no coincide con imponible más no imponible.', 'severity' => 'error'];
        }
        if ($totals['total_deductions'] !== $totals['legal_deductions'] + $totals['other_deductions']) {
            $warnings[] = ['code' => 'deduction_total_mismatch', 'message' => 'Total descuentos no coincide con legales más otros.', 'severity' => 'error'];
        }
        if ($totals['net_amount'] !== $totals['gross_total'] - $totals['total_deductions']) {
            $warnings[] = ['code' => 'net_total_mismatch', 'message' => 'El líquido impreso no coincide con haberes menos descuentos.', 'severity' => 'error'];
        }

        foreach (['earnings' => $earnings, 'discounts' => $discounts, 'employer_contributions' => $contributions] as $section => $lines) {
            if ($lines === []) {
                $warnings[] = ['code' => 'empty_'.$section, 'message' => 'No se extrajeron líneas de '.$section.'.', 'severity' => 'error'];
            }
        }

        $baseConfidence = min((float) ($page['confidence'] ?? 1), $this->confidence($page));
        $confidence = max(0, $baseConfidence - (collect($warnings)->where('severity', 'error')->count() * 0.08));

        return [
            'provider' => $this->provider(),
            'parser_version' => $this->version(),
            'page_number' => (int) ($page['page_number'] ?? 1),
            'extraction_method' => $page['extraction_method'] ?? 'native',
            'period' => ['year' => $year, 'month' => $month],
            'establishment' => [
                'rbd' => $this->valueBelow($tokens, 'RBD'),
                'name' => $this->valueBelow($tokens, 'NOMBRE DEL RBD'),
            ],
            'employee' => [
                'rut' => $rutNormalized,
                'name' => $this->valueBelow($tokens, 'TRABAJADOR'),
                'position' => $this->valueBelow($tokens, 'CARGO S'),
                'started_at' => $this->valueBelow($tokens, 'FECHA DE INGRESO'),
                'pension_provider' => $this->valueBelow($tokens, 'PREVISION'),
                'health_institution' => $this->valueBelow($tokens, 'SALUD'),
                'health_plan' => $this->valueBelow($tokens, 'PLAN SALUD'),
                'contract_type' => $this->valueBelow($tokens, 'TIPO DE CONTRATO S'),
                'weekly_hours' => $this->numericValueBelow($tokens, 'CARGA HORARIA'),
                'teacher_level' => $this->valueBelow($tokens, 'NIVEL DOCENTE'),
                'bienios' => $this->numericValueBelow($tokens, 'N BIENIOS'),
                'priority_percent' => $this->valueBelow($tokens, '% PRIORITARIOS'),
                'worked_days' => $this->numericValueBelow($tokens, 'DIAS'),
                'leave_days' => $this->numericValueBelow($tokens, 'LICENCIA'),
                'overtime_hours' => $this->numericValueBelow($tokens, 'HORAS EXTRA'),
            ],
            'funding_labels' => $fundingLabels,
            'earnings' => $earnings,
            'discounts' => $discounts,
            'employer_contributions' => $contributions,
            'totals' => $totals,
            'printed_funding_totals' => [
                'taxable' => $this->fundingTotalsAt($tokens, 'TOTAL IMPONIBLE', $earningSources),
                'non_taxable' => $this->fundingTotalsAt($tokens, 'TOTAL NO IMPONIBLE', $earningSources),
                'employer_contributions' => $this->contributionFundingTotals($tokens, $contributionHeader, $contributionSources),
            ],
            'warnings' => $warnings,
            'confidence' => round($confidence, 5),
            'normalized_text' => $this->sanitizedText((string) ($page['text'] ?? '')),
            'normalized_structure' => ['funding_labels' => $fundingLabels, 'token_count' => count($tokens)],
        ];
    }

    /** @return array{0:int|null,1:int|null} */
    private function period(string $text): array
    {
        $normalized = $this->normalizer->label($text);
        foreach (self::MONTHS as $label => $month) {
            if (preg_match('/\b'.$label.'\s+(20\d{2})\b/', $normalized, $matches)) {
                return [(int) $matches[1], $month];
            }
        }

        return [null, null];
    }

    private function sanitizedText(string $text): string
    {
        $position = mb_stripos($text, 'Certifico haber recibido');

        return trim($position === false ? $text : mb_substr($text, 0, $position));
    }

    private function valueBelow(array $tokens, string $label): ?string
    {
        $anchor = $this->token($tokens, $label);
        if (! $anchor) {
            return null;
        }

        $candidate = collect($tokens)
            ->filter(fn (array $token): bool => $token['y'] < $anchor['y'] - 0.5
                && $token['y'] >= $anchor['y'] - 32
                && abs($token['x'] - $anchor['x']) <= 12)
            ->sortByDesc('y')
            ->first();

        return $candidate['text'] ?? null;
    }

    private function numericValueBelow(array $tokens, string $label): int|float|null
    {
        $value = $this->valueBelow($tokens, $label);
        if ($value === null || ! preg_match('/-?\d+(?:[.,]\d+)?/', $value, $matches)) {
            return null;
        }

        return str_contains($matches[0], '.') || str_contains($matches[0], ',')
            ? (float) str_replace(',', '.', $matches[0])
            : (int) $matches[0];
    }

    private function token(array $tokens, string $label): ?array
    {
        $needle = $this->normalizer->label($label);

        return collect($tokens)->first(fn (array $token): bool => $this->normalizer->label($token['text']) === $needle);
    }

    private function tokenStartsWith(array $tokens, string $label): ?array
    {
        $needle = $this->normalizer->label($label);

        return collect($tokens)->first(fn (array $token): bool => str_starts_with($this->normalizer->label($token['text']), $needle));
    }

    /** @return array<int,array{label:string,x:float}> */
    private function sourcesAtY(array $tokens, float $y): array
    {
        return collect($tokens)
            ->filter(fn (array $token): bool => abs($token['y'] - $y) <= 2 && $token['x'] > 100 && $token['x'] < 500)
            ->reject(fn (array $token): bool => in_array($this->normalizer->label($token['text']), ['TOTAL', 'MONTO'], true))
            ->map(fn (array $token): array => ['label' => $this->normalizer->label($token['text']), 'x' => (float) $token['x']])
            ->sortBy('x')->values()->all();
    }

    private function contributionSources(array $tokens, array $header): array
    {
        $totalHeader = collect($tokens)
            ->filter(fn (array $token): bool => $this->normalizer->label($token['text']) === 'TOTAL'
                && $token['x'] > 500 && $token['y'] < $header['y'] + 5 && $token['y'] > $header['y'] - 20)
            ->sortByDesc('y')->first();

        return $totalHeader ? $this->sourcesAtY($tokens, (float) $totalHeader['y']) : [];
    }

    private function parseEarnings(array $tokens, array $header, array $discountsHeader, array $sources): array
    {
        $taxableTotal = $this->token($tokens, 'TOTAL IMPONIBLE');
        $sectionTokens = collect($tokens)
            ->filter(fn (array $token): bool => $token['y'] < $header['y'] - 0.4 && $token['y'] > $discountsHeader['y'] + 0.4)
            ->values();
        $labels = $sectionTokens
            ->filter(fn (array $token): bool => $token['x'] < 120 && preg_match('/^\s*\(\d{4}\)/', $token['text']))
            ->sortByDesc('y')
            ->values();
        $markers = $sectionTokens
            ->filter(fn (array $token): bool => $token['x'] < 120 && str_starts_with($this->normalizer->label($token['text']), 'TOTAL '))
            ->values();
        $result = [];
        $lineNumber = 0;
        foreach ($labels as $labelIndex => $labelToken) {
            $lineNumber++;
            $nextLabelY = isset($labels[$labelIndex + 1]) ? (float) $labels[$labelIndex + 1]['y'] : (float) $discountsHeader['y'];
            $nextMarkerY = $markers
                ->filter(fn (array $marker): bool => $marker['y'] < $labelToken['y'] - 0.4)
                ->max('y');
            $lowerY = max($nextLabelY, (float) ($nextMarkerY ?: $discountsHeader['y']));
            $lineTokens = $sectionTokens
                ->filter(fn (array $token): bool => $token['y'] <= $labelToken['y'] + 0.5 && $token['y'] > $lowerY + 0.5)
                ->values()->all();
            $continuation = collect($lineTokens)
                ->filter(fn (array $token): bool => $token['x'] < 120 && $token !== $labelToken)
                ->pluck('text')->implode(' ');
            $fullLabel = trim($labelToken['text'].' '.$continuation);
            foreach ($this->sourceAmounts($lineTokens, $sources) as $amount) {
                $result[] = [
                    'code' => $this->normalizer->conceptCode($fullLabel),
                    'description' => $this->normalizer->conceptDescription($fullLabel),
                    'original_label' => $fullLabel,
                    'line_number' => $lineNumber,
                    'is_imponible' => ! $taxableTotal || $labelToken['y'] > $taxableTotal['y'],
                    'funding_label' => $amount['source'],
                    'amount' => $amount['amount'],
                ];
            }
        }

        return $result;
    }

    private function parseDiscounts(array $tokens, array $header, array $netHeader): array
    {
        $legalTotal = $this->token($tokens, 'TOTAL DESC LEGALES');
        $rows = $this->rowsBetween($tokens, (float) $header['y'], (float) $netHeader['y']);
        $result = [];
        $lineNumber = 0;
        foreach ($rows as $row) {
            $labelToken = collect($row)->first(fn (array $token): bool => $token['x'] < 120 && preg_match('/^\s*\(\d{4}\)/', $token['text']));
            if (! $labelToken) {
                continue;
            }
            $amountToken = collect($row)->filter(fn (array $token): bool => $token['x'] > 120 && $token['x'] < 500 && $this->isMoney($token['text']))->sortBy('x')->first();
            if (! $amountToken) {
                continue;
            }
            $lineNumber++;
            $legal = $legalTotal && $labelToken['y'] > $legalTotal['y'];
            $code = $this->normalizer->conceptCode($labelToken['text']);
            $result[] = [
                'code' => $code,
                'description' => $this->normalizer->conceptDescription($labelToken['text']),
                'original_label' => $labelToken['text'],
                'line_number' => $lineNumber,
                'amount' => $this->normalizer->money($amountToken['text']),
                'classification' => $legal ? ($code === '2005' ? 'impuesto_unico' : 'legal_previsional') : 'other',
                'payment_destination' => $legal ? ($code === '2005' ? 'Formulario 29' : 'Previred') : 'Otras retenciones',
                'distribution_base' => $legal ? 'imponible' : 'haberes',
            ];
        }

        return $result;
    }

    private function parseContributions(array $tokens, array $header, array $sources): array
    {
        if ($sources === []) {
            return [];
        }
        $sourceHeaderY = collect($tokens)
            ->filter(fn (array $token): bool => $token['x'] > 500 && $this->normalizer->label($token['text']) === 'TOTAL'
                && $token['y'] < $header['y'] + 5 && $token['y'] > $header['y'] - 20)
            ->max('y');
        $totalRow = collect($tokens)
            ->filter(fn (array $token): bool => $token['x'] < 100 && $this->normalizer->label($token['text']) === 'TOTAL'
                && $token['y'] < $sourceHeaderY)
            ->sortByDesc('y')->first();
        if (! $sourceHeaderY || ! $totalRow) {
            return [];
        }

        $rows = $this->rowsBetween($tokens, (float) $sourceHeaderY, (float) $totalRow['y']);
        $result = [];
        $lineNumber = 0;
        foreach ($rows as $row) {
            $labelToken = collect($row)->first(fn (array $token): bool => $token['x'] < 120
                && ! in_array($this->normalizer->label($token['text']), ['TOTAL', 'INFORMATIVO'], true));
            if (! $labelToken) {
                continue;
            }
            $lineNumber++;
            foreach ($this->sourceAmounts($row, $sources, true) as $amount) {
                $result[] = [
                    'code' => null,
                    'description' => trim($labelToken['text']),
                    'original_label' => trim($labelToken['text']),
                    'line_number' => $lineNumber,
                    'funding_label' => $amount['source'],
                    'amount' => $amount['amount'],
                ];
            }
        }

        return $result;
    }

    /** @return array<int,array<int,array{x:float,y:float,text:string}>> */
    private function rowsBetween(array $tokens, float $upperY, float $lowerY): array
    {
        $rows = [];
        foreach ($tokens as $token) {
            if ($token['y'] >= $upperY - 0.4 || $token['y'] <= $lowerY + 0.4) {
                continue;
            }
            $key = number_format($token['y'], 1, '.', '');
            $rows[$key][] = $token;
        }
        uksort($rows, fn (string $a, string $b): int => (float) $b <=> (float) $a);
        foreach ($rows as &$row) {
            usort($row, fn (array $a, array $b): int => $a['x'] <=> $b['x']);
        }

        return array_values($rows);
    }

    /** @return array<int,array{source:string,amount:int}> */
    private function sourceAmounts(array $row, array $sources, bool $includeZero = false): array
    {
        $amounts = collect($row)
            ->filter(fn (array $token): bool => $token['x'] > 120 && $token['x'] < 500 && $this->isMoney($token['text']))
            ->map(function (array $token) use ($sources): array {
                $source = collect($sources)->sortBy(fn (array $candidate): float => abs($candidate['x'] - $token['x']))->first();

                return ['source' => $source['label'], 'amount' => $this->normalizer->money($token['text'])];
            })->values();

        if ($includeZero) {
            $bySource = $amounts->keyBy('source');

            return collect($sources)->map(fn (array $source): array => $bySource->get($source['label'], ['source' => $source['label'], 'amount' => 0]))->values()->all();
        }

        return $amounts->all();
    }

    private function isMoney(string $value): bool
    {
        return (bool) preg_match('/^\s*\$?\s*-?\d{1,3}(?:\.\d{3})*\s*$/', $value);
    }

    private function totalAt(array $tokens, string $label, float $minimumX): int
    {
        $anchor = $this->token($tokens, $label);

        return $anchor ? $this->moneyRightOf($tokens, (float) $anchor['y'], $minimumX) : 0;
    }

    private function moneyRightOf(array $tokens, float $y, float $minimumX): int
    {
        $token = collect($tokens)->filter(fn (array $token): bool => abs($token['y'] - $y) <= 0.5 && $token['x'] > $minimumX && $this->isMoney($token['text']))->sortByDesc('x')->first();

        return $token ? $this->normalizer->money($token['text']) : 0;
    }

    private function fundingTotalsAt(array $tokens, string $label, array $sources): array
    {
        $anchor = $this->token($tokens, $label);
        if (! $anchor) {
            return [];
        }
        $row = collect($tokens)->filter(fn (array $token): bool => abs($token['y'] - $anchor['y']) <= 0.5)->values()->all();

        return collect($this->sourceAmounts($row, $sources, true))->pluck('amount', 'source')->all();
    }

    private function contributionTotal(array $tokens, ?array $header): int
    {
        if (! $header) {
            return 0;
        }
        $totalRow = collect($tokens)->filter(fn (array $token): bool => $token['x'] < 100
            && $this->normalizer->label($token['text']) === 'TOTAL' && $token['y'] < $header['y'])->sortByDesc('y')->first();

        return $totalRow ? $this->moneyRightOf($tokens, (float) $totalRow['y'], 500) : 0;
    }

    private function contributionFundingTotals(array $tokens, ?array $header, array $sources): array
    {
        if (! $header) {
            return [];
        }
        $totalRow = collect($tokens)->filter(fn (array $token): bool => $token['x'] < 100
            && $this->normalizer->label($token['text']) === 'TOTAL' && $token['y'] < $header['y'])->sortByDesc('y')->first();
        if (! $totalRow) {
            return [];
        }
        $row = collect($tokens)->filter(fn (array $token): bool => abs($token['y'] - $totalRow['y']) <= 0.5)->values()->all();

        return collect($this->sourceAmounts($row, $sources, true))->pluck('amount', 'source')->all();
    }
}
