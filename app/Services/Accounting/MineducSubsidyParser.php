<?php

namespace App\Services\Accounting;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;
use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;

class MineducSubsidyParser
{
    public const VERSION = '1.2';

    /**
     * @return array<string, mixed>
     */
    public function parse(string $path, string $originalName): array
    {
        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new RuntimeException('No se pudo leer el archivo cargado.');
        }

        if (str_starts_with($bytes, '%PDF-')) {
            return $this->parsePdf($path, $originalName);
        }

        if (stripos($bytes, '<html') !== false || stripos($bytes, '<table') !== false) {
            return $this->parseHtml($bytes, $originalName);
        }

        throw new RuntimeException('Formato no soportado. Se aceptan PDF y anexos MINEDUC HTML con extensión XLS.');
    }

    /**
     * @return array<string, mixed>
     */
    private function parsePdf(string $path, string $originalName): array
    {
        $text = (new PdfParser)->parseFile($path)->getText();
        $plain = $this->plainText($text);
        [$rbd, $period] = $this->extractIdentity($plain, $originalName);

        $lines = [];
        $definitions = [
            ['subsidy_base', 'Subvención base', 'haber', 1, '/Subtotal\s+Subvenci[oó]n\s+Base\s*\$\s*([\d.,]+)/iu'],
            ['zone_increment', 'Incremento zona', 'suplemento', 1, '/Incremento\s+Zona\s*\$\s*([\d.,]+)/iu'],
            ['gratuity', 'Aporte por gratuidad', 'suplemento', 1, '/Aporte\s+por\s+Gratuidad\s*\$\s*([\d.,]+)/iu'],
            ['law_19464', 'Monto Ley 19.464', 'suplemento', 1, '/Monto\s+Ley\s+19\.?464\s*\$\s*([\d.,]+)/iu'],
            ['pension_reform', 'Reforma de pensiones', 'suplemento', 1, '/Reforma\s+de\s+Pensiones(?:\(\d+\))?\s*\$\s*([\d.,]+)/iu'],
            ['reliquidation', 'Reliquidación', 'reliquidacion', 1, '/Reliquidaci[oó]n[^$]{0,80}\$\s*([\d.,]+)/iu'],
            ['discounts', 'Descuentos', 'descuento', -1, '/Total\s+Descuentos(?:\(\d+\))?\s*\$\s*([\d.,]+)/iu'],
            ['withholdings', 'Retenciones', 'retencion', -1, '/Total\s+Retenciones(?:\(\d+\))?\s*\$\s*([\d.,]+)/iu'],
        ];

        foreach ($definitions as [$code, $name, $classification, $sign, $pattern]) {
            if (preg_match($pattern, $plain, $matches)) {
                $amount = $this->parseMoney($matches[1]);
                $amount = $sign < 0 ? abs($amount) : $amount;
                if ($amount > 0 || in_array($code, ['subsidy_base', 'zone_increment', 'gratuity', 'law_19464', 'pension_reform'], true)) {
                    $lines[] = $this->line($code, $name, $classification, $sign, $amount, false, false, []);
                }
            }
        }

        $declaredTotal = $this->firstMoney($plain, [
            '/L[ií]quido\s+final[^$]{0,100}\$\s*([\d.,]+)/iu',
            '/L[ií]quido\s+a\s+pagar[^$]{0,100}\$\s*([\d.,]+)/iu',
        ]);

        if ($lines === [] && $declaredTotal > 0) {
            $lines[] = $this->line('summary_net', 'Líquido informado', 'haber', 1, $declaredTotal, false, false, []);
        }

        return [
            'detected_format' => 'pdf',
            'source_type' => 'normal_payment_order',
            'family' => 'normal',
            'rbd' => $rbd,
            'period' => $period,
            'declared_total' => $declaredTotal,
            'lines' => $lines,
            'warnings' => $lines === [] ? ['No se encontraron conceptos reconocibles en la orden de pago.'] : [],
            'metadata' => ['original_filename' => $originalName],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseHtml(string $html, string $originalName): array
    {
        $plain = $this->plainText(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        [$rbd, $period] = $this->extractIdentity($plain, $originalName);
        $documentKey = Str::lower(Str::ascii($originalName.' '.$plain));
        if (str_contains($documentKey, 'orden_pago') || str_contains($documentKey, 'resumen conceptos calculados')) {
            return $this->parseHtmlPaymentOrder($plain, $originalName, $rbd, $period);
        }
        if (str_contains($documentKey, 'proretencion') || str_contains($documentKey, 'pro retencion')) {
            return $this->parseProRetentionRoster($html, $originalName, $rbd, $period);
        }
        if (str_contains($documentKey, 'bono escolar') && str_contains($documentKey, 'trabajador')) {
            return $this->parseSchoolBonusRoster($html, $originalName, $rbd, $period);
        }
        $spec = $this->htmlSpec($originalName, $plain);

        if ($spec['concept_code'] === 'pie_breakdown') {
            return $this->parsePieAnnex($html, $plain, $originalName, $rbd, $period);
        }

        $table = $this->findDetailTable($html, $spec['amount_headers']);
        $headers = $table['headers'];
        $rows = $table['rows'];
        $indexes = $this->resolveIndexes($headers, $spec);
        $allocations = [];

        foreach ($rows as $row) {
            $teachingCode = $this->numericCode($row[$indexes['code']] ?? null);
            if ($teachingCode === null) {
                continue;
            }

            $amount = $this->parseMoney($row[$indexes['amount']] ?? null);
            $grade = $this->numericCode($row[$indexes['grade']] ?? null);
            $payload = [];
            foreach ($headers as $index => $header) {
                $payload[$header] = $row[$index] ?? null;
            }

            $allocations[] = [
                'teaching_code' => (string) $teachingCode,
                'grade_code' => $grade === null ? null : (string) $grade,
                'course_letter' => $this->nullableText($row[$indexes['letter']] ?? null),
                'education_label' => $this->nullableText($row[$indexes['label']] ?? null),
                'enrollment' => $this->parseLocalizedDecimal($row[$indexes['enrollment']] ?? null),
                'attendance_average' => $this->parseLocalizedDecimal($row[$indexes['attendance']] ?? null),
                'use_factor' => $this->parseLocalizedDecimal($row[$indexes['factor']] ?? null),
                'amount' => $amount,
                'source_payload' => $payload,
                'source_row_hash' => hash('sha256', $spec['concept_code'].'|'.json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            ];
        }

        if ($allocations === []) {
            throw new RuntimeException('El anexo no contiene filas de detalle reconocibles.');
        }

        $total = array_sum(array_column($allocations, 'amount'));
        $line = $this->line(
            $spec['concept_code'],
            $spec['concept_name'],
            $spec['classification'],
            1,
            $total,
            false,
            true,
            $allocations,
        );

        return [
            'detected_format' => 'html_xls',
            'source_type' => $spec['source_type'],
            'family' => $spec['family'],
            'rbd' => $rbd,
            'period' => $period,
            'declared_total' => $total,
            'lines' => [$line],
            'warnings' => [],
            'metadata' => ['original_filename' => $originalName, 'row_count' => count($allocations)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseProRetentionRoster(
        string $html,
        string $originalName,
        string $rbd,
        CarbonImmutable $period,
    ): array {
        $table = $this->findTableWithHeaders($html, [
            'periodo',
            'rbd',
            'codigo ensenanza',
            'curso',
            'rut alumno',
            'tramo',
            'valor tramo',
        ]);
        $headers = $table['headers'];
        $indexes = [
            'period' => $this->headerIndex($headers, ['periodo']),
            'rbd' => $this->headerIndex($headers, ['rbd']),
            'teaching_code' => $this->headerIndex($headers, ['codigo ensenanza']),
            'teaching_label' => $this->headerIndex($headers, ['glosa tipo ensenanza']),
            'grade' => $this->headerIndex($headers, ['curso']),
            'course_label' => $this->headerIndex($headers, ['glosa curso']),
            'student_rut' => $this->headerIndex($headers, ['rut alumno']),
            'student_name' => $this->headerIndex($headers, ['nombre alumno']),
            'tranche' => $this->headerIndex($headers, ['tramo']),
            'amount' => $this->headerIndex($headers, ['valor tramo']),
        ];
        $allocations = [];
        $tranches = [];

        foreach ($table['rows'] as $row) {
            $teachingCode = $this->numericCode($row[$indexes['teaching_code']] ?? null);
            $grade = $this->numericCode($row[$indexes['grade']] ?? null);
            $rowRbd = $this->numericCode($row[$indexes['rbd']] ?? null);
            $amount = $this->parseMoney($row[$indexes['amount']] ?? null);
            if ($teachingCode === null || $grade === null || $rowRbd === null || $amount <= 0) {
                continue;
            }

            $payload = $this->rowPayload($headers, $row);
            $payload['_pro_retention'] = [
                'source_period' => $this->nullableText($row[$indexes['period']] ?? null),
                'student_rut' => $this->nullableText($row[$indexes['student_rut']] ?? null),
                'student_name' => $this->nullableText($row[$indexes['student_name']] ?? null),
                'teaching_label' => $this->nullableText($row[$indexes['teaching_label']] ?? null),
                'course_label' => $this->nullableText($row[$indexes['course_label']] ?? null),
                'tranche' => $this->numericCode($row[$indexes['tranche']] ?? null),
                'tranche_amount' => $amount,
            ];
            $tranche = (string) ($payload['_pro_retention']['tranche'] ?? 'sin_tramo');
            $tranches[$tranche] ??= ['students' => 0, 'amount' => 0];
            $tranches[$tranche]['students']++;
            $tranches[$tranche]['amount'] += $amount;

            $allocations[] = [
                'teaching_code' => (string) $teachingCode,
                'grade_code' => (string) $grade,
                'course_letter' => null,
                'education_label' => $this->nullableText($row[$indexes['course_label']] ?? null),
                'enrollment' => 1,
                'attendance_average' => null,
                'use_factor' => $payload['_pro_retention']['tranche'],
                'amount' => $amount,
                'source_payload' => $payload,
                'source_row_hash' => hash('sha256', 'pro_retention|'.json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            ];
        }

        if ($allocations === []) {
            throw new RuntimeException('La nómina Pro-Retención no contiene estudiantes reconocibles.');
        }

        $total = array_sum(array_column($allocations, 'amount'));

        return [
            'detected_format' => 'html_xls',
            'source_type' => 'pro_retention_student_roster',
            'family' => 'pro_retention',
            'rbd' => $rbd,
            'period' => $period,
            'declared_total' => $total,
            'lines' => [$this->line(
                'pro_retention',
                'Subvención Pro-Retención',
                'haber',
                1,
                $total,
                false,
                true,
                $allocations,
                ['student_count' => count($allocations), 'tranches' => $tranches],
            )],
            'warnings' => [],
            'metadata' => [
                'original_filename' => $originalName,
                'student_count' => count($allocations),
                'tranches' => $tranches,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseSchoolBonusRoster(
        string $html,
        string $originalName,
        string $rbd,
        CarbonImmutable $period,
    ): array {
        $table = $this->findTableWithHeaders($html, [
            'periodo',
            'rbd',
            'rut trabajador',
            'rutcarga',
            'monto por bescolar',
            'monto por adicional',
        ]);
        $headers = $table['headers'];
        $indexes = [
            'period' => $this->headerIndex($headers, ['periodo']),
            'rbd' => $this->headerIndex($headers, ['rbd']),
            'worker_rut' => $this->headerIndex($headers, ['rut trabajador']),
            'worker_name' => $this->headerIndex($headers, ['nombre']),
            'worker_type' => $this->headerIndex($headers, ['tipo persona']),
            'hours' => $this->headerIndex($headers, ['n horas', 'na horas']),
            'tranche' => $this->headerIndex($headers, ['tramo']),
            'higher_tranche' => $this->headerIndex($headers, ['tramo mayor']),
            'dependent_rut' => $this->headerIndex($headers, ['rutcarga']),
            'dependent_name' => $this->headerIndex($headers, ['nombrecarga']),
            'bonus_amount' => $this->headerIndex($headers, ['monto por bescolar']),
            'additional_amount' => $this->headerIndex($headers, ['monto por adicional']),
        ];
        $allocations = [];
        $workerRuts = [];
        $bonusTotal = 0;
        $additionalTotal = 0;

        foreach ($table['rows'] as $row) {
            $rowRbd = $this->numericCode($row[$indexes['rbd']] ?? null);
            $workerRut = $this->nullableText($row[$indexes['worker_rut']] ?? null);
            $dependentRut = $this->nullableText($row[$indexes['dependent_rut']] ?? null);
            $bonusAmount = $this->parseMoney($row[$indexes['bonus_amount']] ?? null);
            $additionalAmount = $this->parseMoney($row[$indexes['additional_amount']] ?? null);
            if ($rowRbd === null || $workerRut === null || $dependentRut === null || ($bonusAmount + $additionalAmount) <= 0) {
                continue;
            }

            $payload = $this->rowPayload($headers, $row);
            $payload['_school_bonus'] = [
                'source_period' => $this->nullableText($row[$indexes['period']] ?? null),
                'worker_rut' => $workerRut,
                'worker_name' => $this->nullableText($row[$indexes['worker_name']] ?? null),
                'worker_type' => $this->nullableText($row[$indexes['worker_type']] ?? null),
                'hours' => $this->parseLocalizedDecimal($row[$indexes['hours']] ?? null),
                'tranche' => $this->numericCode($row[$indexes['tranche']] ?? null),
                'higher_tranche' => $this->numericCode($row[$indexes['higher_tranche']] ?? null),
                'dependent_rut' => $dependentRut,
                'dependent_name' => $this->nullableText($row[$indexes['dependent_name']] ?? null),
                'bonus_amount' => $bonusAmount,
                'additional_amount' => $additionalAmount,
            ];
            $amount = $bonusAmount + $additionalAmount;
            $workerRuts[$workerRut] = true;
            $bonusTotal += $bonusAmount;
            $additionalTotal += $additionalAmount;

            $allocations[] = [
                'teaching_code' => null,
                'grade_code' => null,
                'course_letter' => null,
                'education_label' => $payload['_school_bonus']['worker_type'],
                'enrollment' => 1,
                'attendance_average' => null,
                'use_factor' => $payload['_school_bonus']['hours'],
                'amount' => $amount,
                'source_payload' => $payload,
                'source_row_hash' => hash('sha256', 'school_bonus|'.json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            ];
        }

        if ($allocations === []) {
            throw new RuntimeException('La nómina de Bono Escolar no contiene cargas reconocibles.');
        }

        $total = $bonusTotal + $additionalTotal;
        $components = [
            'bonus_amount' => $bonusTotal,
            'additional_amount' => $additionalTotal,
        ];

        return [
            'detected_format' => 'html_xls',
            'source_type' => 'school_bonus_worker_roster',
            'family' => 'school_bonus',
            'rbd' => $rbd,
            'period' => $period,
            'declared_total' => $total,
            'lines' => [$this->line(
                'school_bonus',
                'Bono Escolar y adicional',
                'haber',
                1,
                $total,
                false,
                false,
                $allocations,
                [
                    'worker_count' => count($workerRuts),
                    'dependent_count' => count($allocations),
                    'bonus_components' => $components,
                    'education_distribution_not_applicable' => true,
                ],
            )],
            'warnings' => ['La nómina de Bono Escolar se desglosa por trabajador y carga; no corresponde asignarla por nivel educativo.'],
            'metadata' => [
                'original_filename' => $originalName,
                'worker_count' => count($workerRuts),
                'dependent_count' => count($allocations),
                'bonus_components' => $components,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parsePieAnnex(
        string $html,
        string $plain,
        string $originalName,
        string $rbd,
        CarbonImmutable $period,
    ): array {
        $declaredTotal = $this->firstMoney($plain, [
            '/Total\s+montos\s+pagados\s+PIE\s*\$\s*([\d.,]+)/iu',
        ]);
        $declaredBase = $this->firstMoney($plain, [
            '/(?:^|\s)Subvenci[oó]n\s*\$\s*([\d.,]+)/iu',
        ]);
        $declaredZone = $this->firstMoney($plain, [
            '/Incremento\s+Zona(?:\s*\([^)]*\))?\s*\$\s*([\d.,]+)/iu',
        ]);
        $declaredLaw19410 = $this->firstMoney($plain, [
            '/Monto\s*\(Adic\)\s*Ley\s*19\.?410\s*\$\s*([\d.,]+)/iu',
        ]);
        $declaredLaw19464 = $this->firstMoney($plain, [
            '/Monto\s*\(No\s*doc\)\s*Ley\s*19\.?464\s*\$\s*([\d.,]+)/iu',
        ]);
        $declaredNonTeacherZone = $this->firstMoney($plain, [
            '/Monto\s+Incremento\s+Zona\s*\(No\s*doc\)(?:\s*\([^)]*\))?\s*\$\s*([\d.,]+)/iu',
        ]);
        $declaredNonTeacherTotal = $this->firstMoney($plain, [
            '/Total\s+No\s+Docente\s*\$\s*([\d.,]+)/iu',
        ]);
        $allocations = [];
        $rowCount = 0;
        $rowBaseTotal = 0;
        $rowRuralityTotal = 0;
        $rowLaw19410Total = 0;
        $rowLaw19464Total = 0;
        $rowLaw19933Total = 0;

        try {
            $table = $this->findDetailTable($html, ['subvencion']);
            $headers = $table['headers'];
            $normalized = array_map(fn (string $header): string => $this->normalizeHeader($header), $headers);
            $findExact = static fn (string $value): ?int => (($index = array_search($value, $normalized, true)) === false ? null : $index);
            $findContains = static function (array $needles) use ($normalized): ?int {
                foreach ($normalized as $index => $header) {
                    foreach ($needles as $needle) {
                        if (str_contains($header, $needle)) {
                            return $index;
                        }
                    }
                }

                return null;
            };
            $indexes = [
                'code' => $findExact('cod ens') ?? $findContains(['cod ens', 'codigo ensenanza']),
                'grade' => $findExact('grado') ?? $findContains(['grado']),
                'jec' => $findExact('jec'),
                'letter' => $findContains(['letra']),
                'education_code' => $findExact('ens'),
                'level' => $findExact('nivel'),
                'label' => $findContains(['glosa subvencion', 'glosa']),
                'enrollment' => $findContains(['matricula']),
                'attendance' => $findExact('promedio'),
                'factor' => $findExact('factor'),
                'base' => $findExact('subvencion'),
                'rurality' => $findExact('monto ruralidad'),
                'law_19410' => $findContains(['total monto adic ley 19 410']),
                'law_19464' => $findContains(['total monto no doc ley 19 464']),
                'law_19933' => $findContains(['monto ley 19 933']),
            ];

            if ($indexes['code'] === null || $indexes['grade'] === null || $indexes['base'] === null) {
                throw new RuntimeException('El anexo PIE cambió sus encabezados obligatorios.');
            }

            foreach ($table['rows'] as $row) {
                $teachingCode = $this->numericCode($row[$indexes['code']] ?? null);
                if ($teachingCode === null) {
                    continue;
                }

                $grade = $this->numericCode($row[$indexes['grade']] ?? null);
                $baseAmount = $this->parseMoney($row[$indexes['base']] ?? null);
                $ruralityAmount = $this->parseMoney($row[$indexes['rurality']] ?? null);
                $law19410Amount = $this->parseMoney($row[$indexes['law_19410']] ?? null);
                $law19464Amount = $this->parseMoney($row[$indexes['law_19464']] ?? null);
                $law19933Amount = $this->parseMoney($row[$indexes['law_19933']] ?? null);
                $payload = [];
                foreach ($headers as $index => $header) {
                    $payload[$header] = $row[$index] ?? null;
                }
                $payload['_pie'] = [
                    'jec' => $this->nullableText($row[$indexes['jec']] ?? null),
                    'education_code' => $this->nullableText($row[$indexes['education_code']] ?? null),
                    'level_code' => $this->nullableText($row[$indexes['level']] ?? null),
                    'base_amount' => $baseAmount,
                    'rurality_amount' => $ruralityAmount,
                    'zone_increment_amount' => 0,
                    'law_19410_amount' => $law19410Amount,
                    'law_19464_amount' => $law19464Amount,
                    'law_19933_reference' => $law19933Amount,
                ];

                $allocations[] = [
                    'teaching_code' => (string) $teachingCode,
                    'grade_code' => $grade === null ? null : (string) $grade,
                    'course_letter' => $this->nullableText($row[$indexes['letter']] ?? null),
                    'education_label' => $this->nullableText($row[$indexes['label']] ?? null),
                    'enrollment' => $this->parseLocalizedDecimal($row[$indexes['enrollment']] ?? null),
                    'attendance_average' => $this->parseLocalizedDecimal($row[$indexes['attendance']] ?? null),
                    'use_factor' => $this->parseLocalizedDecimal($row[$indexes['factor']] ?? null),
                    'amount' => $baseAmount + $ruralityAmount,
                    'source_payload' => $payload,
                    'source_row_hash' => hash('sha256', 'pie_breakdown|'.json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                ];
                $rowBaseTotal += $baseAmount;
                $rowRuralityTotal += $ruralityAmount;
                $rowLaw19410Total += $law19410Amount;
                $rowLaw19464Total += $law19464Amount;
                $rowLaw19933Total += $law19933Amount;
                $rowCount++;
            }
        } catch (RuntimeException) {
            // Some historical PIE exports only contain the summary. They remain
            // importable and can still be reported without row-level allocation.
        }

        $rowCoreTotal = $rowBaseTotal + $rowRuralityTotal;
        $amount = $declaredTotal > 0
            ? $declaredTotal
            : $rowCoreTotal + $declaredZone;
        $zoneToAllocate = $allocations === []
            ? 0
            : max(0, $amount - $rowCoreTotal);
        $zoneByRow = $this->allocateProportionally(
            $zoneToAllocate,
            array_column($allocations, 'amount'),
        );

        foreach ($allocations as $index => &$allocation) {
            $zoneAmount = $zoneByRow[$index] ?? 0;
            $allocation['amount'] += $zoneAmount;
            $allocation['source_payload']['_pie']['zone_increment_amount'] = $zoneAmount;
            $allocation['source_row_hash'] = hash(
                'sha256',
                'pie_breakdown|'.json_encode($allocation['source_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            );
        }
        unset($allocation);

        $components = [
            'base_amount' => $declaredBase ?: $rowBaseTotal,
            'rurality_amount' => $rowRuralityTotal,
            'zone_increment_amount' => $declaredZone ?: $zoneToAllocate,
            'law_19410_amount' => $declaredLaw19410 ?: $rowLaw19410Total,
            'law_19464_amount' => $declaredLaw19464 ?: $rowLaw19464Total,
            'non_teacher_zone_amount' => $declaredNonTeacherZone,
            'non_teacher_total' => $declaredNonTeacherTotal,
            'law_19933_reference' => $rowLaw19933Total,
        ];

        return [
            'detected_format' => 'html_xls',
            'source_type' => 'normal_pie_annex',
            'family' => 'normal',
            'rbd' => $rbd,
            'period' => $period,
            'declared_total' => $amount,
            'lines' => [$this->line(
                'pie_breakdown',
                'Desglose PIE (informativo)',
                'informativo',
                0,
                $amount,
                true,
                $allocations !== [],
                $allocations,
                ['pie_components' => $components, 'row_count' => $rowCount],
            )],
            'warnings' => ['El monto PIE es informativo y no se suma nuevamente a la Subvención Normal.'],
            'metadata' => [
                'original_filename' => $originalName,
                'row_count' => $rowCount,
                'pie_components' => $components,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseHtmlPaymentOrder(
        string $plain,
        string $originalName,
        string $rbd,
        CarbonImmutable $period,
    ): array {
        $key = Str::lower(Str::ascii($plain.' '.$originalName));
        $family = str_contains($key, 'sep prioritario')
            ? 'sep_prioritario'
            : (str_contains($key, 'sep preferente') ? 'sep_preferente' : 'normal');
        $lines = [];

        if ($family === 'normal') {
            $definitions = [
                ['subsidy_base', 'Subvención base', 'haber', 1, '/Subtotal\s+Subvenci[oó]n\s+Base\s*\$\s*([\d.,-]+)/iu'],
                ['zone_increment', 'Incremento zona', 'suplemento', 1, '/Incremento\s+Zona\s*\$\s*([\d.,-]+)/iu'],
                ['gratuity', 'Aporte por gratuidad', 'suplemento', 1, '/Aporte\s+por\s+Gratuidad\s*\$\s*([\d.,-]+)/iu'],
                ['law_19464', 'Monto Ley 19.464', 'suplemento', 1, '/Monto\s+Ley\s+19\.?464\s*\$\s*([\d.,-]+)/iu'],
                ['pension_reform', 'Reforma de pensiones', 'suplemento', 1, '/Reforma\s+de\s+Pensiones(?:\(\d+\))?\s*\$\s*([\d.,-]+)/iu'],
                ['reliquidation', 'Reliquidación', 'reliquidacion', 1, '/Reliquidaci[oó]n[^$]{0,80}\$\s*([\d.,-]+)/iu'],
                ['discounts', 'Descuentos', 'descuento', -1, '/Total\s+Descuentos(?:\(\d+\))?\s*\$\s*([\d.,-]+)/iu'],
                ['withholdings', 'Retenciones', 'retencion', -1, '/Total\s+Retenciones(?:\(\d+\))?\s*\$\s*([\d.,-]+)/iu'],
            ];
        } else {
            $definitions = [
                ['sep_preferential', 'Subvención preferencial', 'haber', 1, '/Subvenci[oó]n\s+Preferencial\s*\$\s*([\d.,-]+)/iu'],
                ['sep_concentration', 'Subvención concentración', 'suplemento', 1, '/Subvenci[oó]n\s+Concentracion\s*\$\s*([\d.,-]+)/iu'],
                ['additional_contribution', 'Aporte adicional', 'suplemento', 1, '/Subvenci[oó]n\s+Aporte\s+Adicional\s*\$\s*([\d.,-]+)/iu'],
                ['extraordinary_contribution', 'Aporte extraordinario', 'suplemento', 1, '/Subvenci[oó]n\s+Aporte\s+Extraordinario\s*\$\s*([\d.,-]+)/iu'],
                ['adjustment', 'Ajustes', 'ajuste', 1, '/Total\s+ajuste(?:\s*\(\d+\))?\s*\$\s*([\d.,-]+)/iu'],
                ['reliquidation', 'Reliquidación', 'reliquidacion', 1, '/Total\s+Reliquidaci[oó]n(?:\s*\(\d+\))?\s*\$\s*([\d.,-]+)/iu'],
            ];
        }

        foreach ($definitions as [$code, $name, $classification, $sign, $pattern]) {
            if (preg_match($pattern, $plain, $matches)) {
                $amount = $this->parseMoney($matches[1]);
                $amount = $sign < 0 ? abs($amount) : $amount;
                if ($amount > 0 || in_array($code, ['subsidy_base', 'zone_increment', 'gratuity', 'law_19464', 'pension_reform', 'sep_preferential', 'sep_concentration', 'additional_contribution'], true)) {
                    $lines[] = $this->line($code, $name, $classification, $sign, $amount, false, false, []);
                }
            }
        }

        $declaredTotal = $this->firstMoney($plain, [
            '/L[ií]quido\s+Pago\s+Final\s*\$\s*([\d.,-]+)/iu',
            '/L[ií]quido\s+final[^$]{0,100}\$\s*([\d.,-]+)/iu',
            '/L[ií]quido\s+a\s+pagar[^$]{0,100}\$\s*([\d.,-]+)/iu',
        ]);

        if ($lines === [] && $declaredTotal > 0) {
            $lines[] = $this->line('summary_net', 'Líquido informado', 'haber', 1, $declaredTotal, false, false, []);
        }

        return [
            'detected_format' => 'html_xls',
            'source_type' => $family.'_payment_order',
            'family' => $family,
            'rbd' => $rbd,
            'period' => $period,
            'declared_total' => $declaredTotal,
            'lines' => $lines,
            'warnings' => $lines === [] ? ['No se encontraron conceptos reconocibles en la orden de pago.'] : [],
            'metadata' => ['original_filename' => $originalName],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function htmlSpec(string $originalName, string $plain): array
    {
        $key = Str::lower(Str::ascii($originalName.' '.$plain));
        $family = str_contains($key, 'sep prioritario')
            ? 'sep_prioritario'
            : (str_contains($key, 'sep preferente') ? 'sep_preferente' : 'normal');

        $specs = [
            'detalle_escolaridad' => ['subsidy_base', 'Subvención base', 'haber', 'normal_schooling_annex', ['subvencion base']],
            'incremento_zona' => ['zone_increment', 'Incremento zona', 'suplemento', 'normal_zone_annex', ['monto incremento zona']],
            'aporte_gratuidad' => ['gratuity', 'Aporte por gratuidad', 'suplemento', 'normal_gratuity_annex', ['subvencion gratuidad']],
            'monto_ley19464' => ['law_19464', 'Monto Ley 19.464', 'suplemento', 'normal_law_19464_annex', ['monto ley 19464']],
            'reforma_pensiones' => ['pension_reform', 'Reforma de pensiones', 'suplemento', 'normal_pension_annex', ['subvencion reforma pensiones']],
            'detalle_pie' => ['pie_breakdown', 'Desglose PIE (informativo)', 'informativo', 'normal_pie_annex', []],
            'subvencion_concentracion' => ['sep_concentration', 'Subvención concentración', 'suplemento', 'sep_concentration_annex', ['total subvencion concentracion']],
            'aporte_adicional' => ['additional_contribution', 'Aporte adicional', 'suplemento', 'sep_additional_annex', ['total aporte adicional']],
        ];

        foreach ($specs as $needle => [$code, $name, $classification, $sourceType, $amountHeaders]) {
            if (str_contains($key, $needle)) {
                return compact('family') + [
                    'concept_code' => $code,
                    'concept_name' => $name,
                    'classification' => $classification,
                    'source_type' => $sourceType,
                    'amount_headers' => $amountHeaders,
                ];
            }
        }

        if (str_contains($key, 'subvencion prioritario') || str_contains($key, 'subvencion preferente')) {
            return compact('family') + [
                'concept_code' => 'sep_preferential',
                'concept_name' => 'Subvención preferencial',
                'classification' => 'haber',
                'source_type' => 'sep_preferential_annex',
                'amount_headers' => ['total subvencion preferencial'],
            ];
        }

        throw new RuntimeException('No se pudo identificar el tipo de anexo MINEDUC.');
    }

    /**
     * @param  array<int, string>  $amountHeaders
     * @return array{headers:array<int,string>,rows:array<int,array<int,string>>}
     */
    private function findDetailTable(string $html, array $amountHeaders): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);

        foreach ($xpath->query('//table') ?: [] as $tableNode) {
            if (! $tableNode instanceof DOMElement) {
                continue;
            }

            $headerRow = null;
            foreach ($xpath->query('.//tr', $tableNode) ?: [] as $candidate) {
                if (($xpath->query('./th', $candidate)?->length ?? 0) > 0) {
                    $headerRow = $candidate;
                    break;
                }
            }

            if (! $headerRow) {
                continue;
            }

            $headers = [];
            foreach ($xpath->query('./th', $headerRow) ?: [] as $cell) {
                $headers[] = $this->plainText($cell->textContent);
            }
            $normalized = array_map(fn (string $header): string => $this->normalizeHeader($header), $headers);
            $hasAmount = collect($amountHeaders)->contains(
                fn (string $expected): bool => in_array($this->normalizeHeader($expected), $normalized, true)
            );
            if (! $hasAmount) {
                continue;
            }

            $rows = [];
            foreach ($xpath->query('.//tr', $tableNode) ?: [] as $rowNode) {
                $cells = [];
                foreach ($xpath->query('./td', $rowNode) ?: [] as $cell) {
                    $cells[] = $this->plainText($cell->textContent);
                }
                if ($cells !== []) {
                    $rows[] = $cells;
                }
            }

            return compact('headers', 'rows');
        }

        throw new RuntimeException('No se encontró la tabla de detalle esperada en el anexo.');
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<string, mixed>  $spec
     * @return array<string, int|null>
     */
    private function resolveIndexes(array $headers, array $spec): array
    {
        $normalized = array_map(fn (string $header): string => $this->normalizeHeader($header), $headers);
        $findExact = static fn (string $value): ?int => (($index = array_search($value, $normalized, true)) === false ? null : $index);
        $findContains = static function (array $needles) use ($normalized): ?int {
            foreach ($normalized as $index => $header) {
                foreach ($needles as $needle) {
                    if (str_contains($header, $needle)) {
                        return $index;
                    }
                }
            }

            return null;
        };

        $amount = null;
        foreach ($spec['amount_headers'] as $amountHeader) {
            $amount = $findExact($this->normalizeHeader($amountHeader));
            if ($amount !== null) {
                break;
            }
        }

        $code = $findExact('cod ens') ?? $findExact('codigo ensenanza') ?? $findContains(['cod ens', 'codigo ensenanza']);
        $grade = $spec['family'] === 'normal'
            ? ($findExact('grado') ?? $findContains(['grado']))
            : $findExact('nivel');

        if ($code === null || $grade === null || $amount === null) {
            throw new RuntimeException('El anexo cambió sus encabezados obligatorios (código, nivel/grado o monto).');
        }

        return [
            'code' => $code,
            'grade' => $grade,
            'amount' => $amount,
            'letter' => $findContains(['letra']),
            'label' => $findContains(['glosa']),
            'enrollment' => $findContains(['matricula']),
            'attendance' => $findContains(['promedio asistencia', 'asistencia promedio', 'promedio alumno']),
            'factor' => $findContains(['factor use', 'factor aporte', 'factor concentracion', 'factor gratuidad', 'factor ley', 'factor pensiones', 'factor']),
        ];
    }

    /**
     * @return array{0:string,1:CarbonImmutable}
     */
    private function extractIdentity(string $plain, ?string $originalName = null): array
    {
        $hasRbd = preg_match('/Establecimiento:\s*(\d+)/iu', $plain, $rbdMatch)
            || ($originalName !== null && preg_match('/RBD[_\s-]+(\d+)/iu', $originalName, $rbdMatch));
        if (! $hasRbd) {
            throw new RuntimeException('No se encontró el RBD del establecimiento.');
        }
        if (! preg_match('/MES\s+PAGO:\s*([A-ZÁÉÍÓÚÑ]+)\s+(\d{4})/iu', $plain, $periodMatch)) {
            throw new RuntimeException('No se encontró el mes de pago.');
        }

        $months = [
            'ENERO' => 1, 'FEBRERO' => 2, 'MARZO' => 3, 'ABRIL' => 4,
            'MAYO' => 5, 'JUNIO' => 6, 'JULIO' => 7, 'AGOSTO' => 8,
            'SEPTIEMBRE' => 9, 'OCTUBRE' => 10, 'NOVIEMBRE' => 11, 'DICIEMBRE' => 12,
        ];
        $monthName = Str::upper(Str::ascii($periodMatch[1]));
        $month = $months[$monthName] ?? null;
        if (! $month) {
            throw new RuntimeException('El mes de pago no es reconocible.');
        }

        return [$rbdMatch[1], CarbonImmutable::create((int) $periodMatch[2], $month, 1)->startOfMonth()];
    }

    /**
     * @param  array<int, string>  $requiredHeaders
     * @return array{headers:array<int,string>,rows:array<int,array<int,string>>}
     */
    private function findTableWithHeaders(string $html, array $requiredHeaders): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $required = array_map(fn (string $header): string => $this->normalizeHeader($header), $requiredHeaders);

        foreach ($xpath->query('//table') ?: [] as $tableNode) {
            if (! $tableNode instanceof DOMElement) {
                continue;
            }

            $headers = [];
            $rows = [];
            $foundHeader = false;
            foreach ($xpath->query('.//tr', $tableNode) ?: [] as $rowNode) {
                $cells = [];
                foreach ($xpath->query('./th|./td', $rowNode) ?: [] as $cell) {
                    $cells[] = $this->plainText($cell->textContent);
                }
                if ($cells === []) {
                    continue;
                }

                if (! $foundHeader) {
                    $normalized = array_map(fn (string $header): string => $this->normalizeHeader($header), $cells);
                    $foundHeader = collect($required)->every(fn (string $header): bool => in_array($header, $normalized, true));
                    if ($foundHeader) {
                        $headers = $cells;
                    }

                    continue;
                }

                $rows[] = $cells;
            }

            if ($foundHeader && $headers !== []) {
                return compact('headers', 'rows');
            }
        }

        throw new RuntimeException('No se encontró la tabla de detalle esperada en el archivo.');
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $candidates
     */
    private function headerIndex(array $headers, array $candidates): int
    {
        $normalized = array_map(fn (string $header): string => $this->normalizeHeader($header), $headers);
        foreach ($candidates as $candidate) {
            $index = array_search($this->normalizeHeader($candidate), $normalized, true);
            if ($index !== false) {
                return $index;
            }
        }

        throw new RuntimeException('El archivo cambió uno de sus encabezados obligatorios.');
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $row
     * @return array<string, string|null>
     */
    private function rowPayload(array $headers, array $row): array
    {
        $payload = [];
        foreach ($headers as $index => $header) {
            $payload[$header] = $row[$index] ?? null;
        }

        return $payload;
    }

    /**
     * @param  array<int, array<string, mixed>>  $allocations
     * @return array<string, mixed>
     */
    private function line(
        string $code,
        string $name,
        string $classification,
        int $sign,
        int|float $amount,
        bool $informative,
        bool $educationAllocable,
        array $allocations,
        array $metadata = [],
    ): array {
        return [
            'concept_code' => $code,
            'concept_name' => $name,
            'classification' => $classification,
            'sign' => $sign,
            'amount' => $amount,
            'declared_amount' => $amount,
            'informative' => $informative,
            'education_allocable' => $educationAllocable,
            'allocations' => $allocations,
            'metadata' => $metadata,
        ];
    }

    /**
     * Distributes an integer total proportionally while preserving the exact sum.
     *
     * @param  array<int, int|float>  $weights
     * @return array<int, int>
     */
    private function allocateProportionally(int $total, array $weights): array
    {
        if ($total <= 0 || $weights === []) {
            return array_fill(0, count($weights), 0);
        }

        $weightTotal = array_sum($weights);
        if ($weightTotal <= 0) {
            return array_fill(0, count($weights), 0);
        }

        $allocated = [];
        $remainders = [];
        foreach ($weights as $index => $weight) {
            $exact = $total * ((float) $weight / (float) $weightTotal);
            $floor = (int) floor($exact);
            $allocated[$index] = $floor;
            $remainders[$index] = $exact - $floor;
        }

        $remaining = $total - array_sum($allocated);
        arsort($remainders, SORT_NUMERIC);
        foreach (array_keys($remainders) as $index) {
            if ($remaining <= 0) {
                break;
            }
            $allocated[$index]++;
            $remaining--;
        }
        ksort($allocated);

        return array_values($allocated);
    }

    private function plainText(?string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }

    private function normalizeHeader(string $header): string
    {
        $header = Str::lower(Str::ascii($this->plainText($header)));

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $header));
    }

    private function parseMoney(mixed $value): int
    {
        $text = trim((string) $value);
        $negative = str_starts_with($text, '-') || (str_starts_with($text, '(') && str_ends_with($text, ')'));
        $digits = preg_replace('/\D/', '', $text);
        $amount = $digits === '' ? 0 : (int) $digits;

        return $negative ? -$amount : $amount;
    }

    private function numericCode(mixed $value): ?int
    {
        $text = trim((string) $value);

        return preg_match('/^\d+(?:\.0+)?$/', $text) ? (int) $text : null;
    }

    private function parseLocalizedDecimal(mixed $value): ?float
    {
        $text = trim((string) $value);
        if ($text === '' || in_array(Str::lower($text), ['nan', 'null'], true)) {
            return null;
        }

        $clean = preg_replace('/[^0-9,.\-]/', '', $text);
        if ($clean === '' || $clean === '-') {
            return null;
        }

        if (str_contains($clean, ',')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } elseif (preg_match('/^\-?\d{1,3}(?:\.\d{3})+$/', $clean)) {
            $clean = str_replace('.', '', $clean);
        }

        return is_numeric($clean) ? (float) $clean : null;
    }

    /**
     * @param  array<int, string>  $patterns
     */
    private function firstMoney(string $plain, array $patterns): int
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $plain, $matches)) {
                return $this->parseMoney($matches[1]);
            }
        }

        return 0;
    }

    private function nullableText(mixed $value): ?string
    {
        $text = $this->plainText((string) $value);

        return $text === '' ? null : $text;
    }
}
