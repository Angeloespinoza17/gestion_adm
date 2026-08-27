<?php

namespace App\Services\PedagogicalManagement;

class InstrumentFieldExtractor
{
    private const SECTION_RULES = [
        'learning_objectives' => '/\b(?:objetivos?\s+de\s+aprendizaje|objetivos?\s+de\s+la\s+evaluaci[oó]n|O\.?\s*A\.?)\b/iu',
        'evaluation_objective' => '/\bobjetivo\s+(?:de\s+)?evaluaci[oó]n\b/iu',
        'indicators' => '/\bindicadores?(?:\s+de\s+evaluaci[oó]n)?\b/iu',
        'capacities' => '/\bcapacidades?\b/iu',
        'skills' => '/\b(?:destrezas?|habilidades?)\b/iu',
        'content' => '/\bcontenido(?:s)?\b/iu',
        'unit' => '/\bunidad\s*(?:N[°ºo]\s*)?\d+/iu',
        'instructions' => '/\binstrucciones?\s+generales?\b/iu',
        'methodology' => '/\bmetodolog[ií]a\b/iu',
        'performance_record' => '/\bregistro\s+de\s+desempe[nñ]o\b/iu',
        'specification_table' => '/\btabla\s+de\s+especificaciones\b/iu',
        'rubric' => '/\br[uú]brica\b/iu',
        'checklist' => '/\blista\s+de\s+cotejo\b/iu',
        'appreciation_scale' => '/\bescala\s+de\s+apreciaci[oó]n\b/iu',
        'self_assessment' => '/\bautoevaluaci[oó]n\b/iu',
        'peer_assessment' => '/\bcoevaluaci[oó]n\b/iu',
        'metacognition' => '/\bmetacognici[oó]n\b/iu',
    ];

    private const COMPONENT_LABELS = [
        'investigacion' => 'investigacion', 'infografia' => 'infografia', 'aspectos formales' => 'aspectos_formales',
        'autoevaluacion' => 'autoevaluacion', 'coevaluacion' => 'coevaluacion', 'metacognicion' => 'metacognicion',
        'seccion i' => 'seccion_i', 'seccion ii' => 'seccion_ii', 'seccion iii' => 'seccion_iii',
    ];

    public function __construct(private readonly InstrumentTextNormalizer $normalizer) {}

    /**
     * @param list<array{page_number:int,raw_text:string,normalized_text:string,has_images:bool,warnings:list<string>}> $pages
     * @return array<string,mixed>
     */
    public function extract(array $pages, string $filename): array
    {
        $sections = [];
        $oaCandidates = [];
        $declaredTotals = [];
        $components = [];
        $percentages = [];
        $durations = [];
        $titles = [];
        $itemHeadings = [];
        $itemPointLines = [];
        $genericAccessibility = [];

        foreach ($pages as $page) {
            $pageNumber = (int) $page['page_number'];
            $text = (string) $page['normalized_text'];
            $lines = array_values(array_filter(explode("\n", $text), fn (string $line): bool => trim($line) !== ''));
            foreach ($lines as $lineIndex => $line) {
                $excerpt = mb_substr(trim($line), 0, 700);
                foreach (self::SECTION_RULES as $section => $pattern) {
                    if (! isset($sections[$section]) && preg_match($pattern, $line) === 1) {
                        $sections[$section] = $this->evidence(true, $pageNumber, $excerpt, 'section_'.$section, 'exact');
                    }
                }
                foreach ($this->oaMatches($line) as $oa) {
                    $key = $oa['normalized_code'].'@'.$pageNumber.'@'.$excerpt;
                    $oaCandidates[$key] = [
                        ...$oa,
                        'page' => $pageNumber,
                        'source_excerpt' => $excerpt,
                        'rule' => 'oa_marker_numeric_sequence_v1',
                        'reliability' => 'exact',
                    ];
                }
                foreach ($this->declaredPointMatches($line) as $value) {
                    $declaredTotals[] = $this->numericEvidence($value, $pageNumber, $excerpt, 'declared_total_label_v1', 'exact');
                }
                foreach ($this->componentMatches($line) as $component) {
                    $components[] = [
                        ...$component,
                        'page' => $pageNumber,
                        'source_excerpt' => $excerpt,
                        'rule' => 'named_point_component_v1',
                        'reliability' => 'exact',
                    ];
                }
                foreach ($this->percentageMatches($line) as $percentage) {
                    $percentages[] = [
                        ...$percentage, 'page' => $pageNumber, 'source_excerpt' => $excerpt,
                        'rule' => 'percentage_with_context_v1', 'reliability' => 'exact',
                    ];
                }
                if (preg_match('/\b(?:duraci[oó]n\s*[:\-]?\s*|contar[aá]s?\s+con\s+|cuenta\s+con\s+)(\d{1,3})\s*minutos?\b/iu', $line, $match) === 1) {
                    $durations[] = $this->numericEvidence((float) $match[1], $pageNumber, $excerpt, 'duration_minutes_v1', 'exact');
                }
                if (preg_match('/\b[ií]tem\s+[ivxlcdm\d]+\b/iu', $line) === 1) {
                    $itemHeadings[] = ['page' => $pageNumber, 'line' => $lineIndex, 'excerpt' => $excerpt];
                    if (preg_match('/\b\d+(?:[,.]\d+)?\s*(?:ptos?\.?|puntos?)\b/iu', $line) === 1) {
                        $itemPointLines[] = $lineIndex.'@'.$pageNumber;
                    }
                }
                if (preg_match('/\b(?:acceso\s+universal|dise[nñ]o\s+universal|adecuaci[oó]n\s+curricular|consideraciones?\s+de\s+acceso)\b/iu', $line) === 1) {
                    $genericAccessibility[] = $this->evidence(true, $pageNumber, $excerpt, 'generic_accessibility_phrase_v1', 'heuristic');
                }
            }
            $titles = [...$titles, ...$this->titleCandidates($lines, $pageNumber)];
        }

        $declaredTotals = $this->uniqueNumericEvidence($declaredTotals);
        $componentSummary = $this->componentSummary($components, $declaredTotals, $pages);
        $allText = implode("\n\n", array_column($pages, 'normalized_text'));

        return [
            'filename' => ['value' => $filename, 'normalized' => $this->normalizer->filenameKey($filename)],
            'title' => $titles[0] ?? null,
            'sections' => $sections,
            'learning_objectives' => array_values($oaCandidates),
            'points' => [
                'declared_totals' => $declaredTotals,
                'components' => $components,
                ...$componentSummary,
            ],
            'percentages' => $percentages,
            'duration' => $durations[0] ?? null,
            'rubric' => [
                'detected' => isset($sections['rubric']),
                'levels_detected' => preg_match_all('/\b[0-4]\s*(?:puntos?|ptos?\.?|pts?\.?)\b/iu', $allText),
                'criteria_identifiable' => preg_match('/\bcriterios?\b/iu', $allText) === 1,
            ],
            'performance_record' => [
                'detected' => isset($sections['performance_record']),
                'has_total' => preg_match('/\btotal\b.{0,30}\b\d+(?:[,.]\d+)?\b/iu', $allText) === 1,
            ],
            'items_without_points' => array_values(array_filter($itemHeadings, fn (array $item): bool => ! in_array($item['line'].'@'.$item['page'], $itemPointLines, true))),
            'generic_accessibility_declaration' => $genericAccessibility[0] ?? null,
            'page_count' => count($pages),
            'text_character_count' => mb_strlen(trim($allText)),
        ];
    }

    /** @return list<array{number:int,normalized_code:string,original:string}> */
    private function oaMatches(string $line): array
    {
        $results = [];
        $marker = '(?:O\.?\s*A\.?|Objetivo(?:s)?\s+de\s+Aprendizaje)';
        preg_match_all('/\b'.$marker.'\s*[-:#]?\s*(\d{1,3})(?:\s*(,|y|e|-)\s*(?:'.$marker.'\s*)?(\d{1,3}))?/iu', $line, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $first = (int) $match[1];
            if ($first < 1 || $first > 200) {
                continue;
            }
            $numbers = [$first];
            $separator = $match[2] ?? null;
            $second = isset($match[3]) && $match[3] !== '' ? (int) $match[3] : null;
            if ($second && $second <= 200) {
                if ($separator === '-' && $second >= $first && ($second - $first) <= 20) {
                    $numbers = range($first, $second);
                } else {
                    $numbers[] = $second;
                }
            }
            foreach (array_unique($numbers) as $number) {
                $results[] = ['number' => $number, 'normalized_code' => $this->normalizer->normalizedOaCode($number), 'original' => trim($match[0])];
            }
        }

        if (preg_match('/\bO\.?\s*A\.?\s*-\s*(\d{1,3})\s+(\d{1,3})\b/iu', $line, $match) === 1) {
            foreach ([(int) $match[1], (int) $match[2]] as $number) {
                $results[] = ['number' => $number, 'normalized_code' => $this->normalizer->normalizedOaCode($number), 'original' => trim($match[0])];
            }
        }

        return collect($results)->unique('normalized_code')->values()->all();
    }

    /** @return list<float> */
    private function declaredPointMatches(string $line): array
    {
        $values = [];
        $patterns = [
            '/\b(?:puntaje\s+(?:total|ideal)|pje\.?\s*total|pt\d?)\s*[:=]?\s*(?:_{1,}\s*\/\s*)?(\d+(?:[,.]\d+)?)\s*(?:ptos?\.?|puntos?)?/iu',
            '/\btotal\s*[:=]\s*(\d+(?:[,.]\d+)?)\s*(?:ptos?\.?|puntos?)\b/iu',
        ];
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $line, $matches);
            foreach ($matches[1] ?? [] as $value) {
                $values[] = $this->decimal($value);
            }
        }

        return $values;
    }

    /** @return list<array{label:string,value:float}> */
    private function componentMatches(string $line): array
    {
        $key = $this->normalizer->searchKey($line);
        $components = [];
        foreach (self::COMPONENT_LABELS as $needle => $label) {
            if (! str_contains($key, $needle) || str_contains($key, 'puntaje total')) {
                continue;
            }
            $escaped = preg_quote($needle, '/');
            if (preg_match('/\b'.$escaped.'\b.{0,80}?(\d+(?:[,.]\d+)?)\s*(?:ptos?\.?|puntos?|pts?\.?)?\b/iu', $key, $match) === 1) {
                $components[] = ['label' => $label, 'value' => $this->decimal($match[1])];
            }
        }

        return $components;
    }

    /** @return list<array{value:float,context:string}> */
    private function percentageMatches(string $line): array
    {
        $values = [];
        if (preg_match_all('/\b(\d{1,3}(?:[,.]\d+)?)\s*%/u', $line, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return [];
        }
        foreach ($matches[1] as [$raw, $offset]) {
            $context = mb_substr($line, max(0, (int) $offset - 55), 130);
            if (preg_match('/\b(?:evaluaci[oó]n|actividad|ponderaci[oó]n|promedio|exigencia|escala|calific)/iu', $context) === 1) {
                $values[] = ['value' => $this->decimal($raw), 'context' => trim($context)];
            }
        }

        return $values;
    }

    /** @param list<string> $lines @return list<array<string,mixed>> */
    private function titleCandidates(array $lines, int $page): array
    {
        if ($page !== 1) {
            return [];
        }
        $candidates = [];
        foreach (array_slice($lines, 0, 24) as $line) {
            $key = $this->normalizer->searchKey($line);
            if (mb_strlen($line) < 8 || mb_strlen($line) > 220) {
                continue;
            }
            if (preg_match('/\b(?:fundacion|colegio|departamento|nombre|curso|fecha|unidad|objetivos?|puntaje|profesor|docente|consideraciones? de acceso|acceso universal|articulo)\b/i', $key) === 1) {
                continue;
            }
            $meaningfulTokens = array_filter(
                preg_split('/\s+/u', $key) ?: [],
                fn (string $token): bool => mb_strlen($token) >= 3
            );
            if (count($meaningfulTokens) < 2) {
                continue;
            }
            $priority = preg_match('/\b(?:evaluacion|prueba|control|rubrica|lista de cotejo|escala de apreciacion|guia|proyecto)\b/i', $key) === 1 ? 0 : 1;
            $candidates[] = [
                'value' => trim($line), 'page' => $page, 'source_excerpt' => trim($line),
                'rule' => 'first_page_title_candidate_v1', 'reliability' => 'heuristic', 'priority' => $priority,
            ];
        }
        usort($candidates, fn (array $a, array $b): int => [$a['priority'], -mb_strlen($a['value'])] <=> [$b['priority'], -mb_strlen($b['value'])]);

        return array_map(function (array $candidate): array {
            unset($candidate['priority']);

            return $candidate;
        }, $candidates);
    }

    /** @param list<array<string,mixed>> $components @param list<array<string,mixed>> $declaredTotals @param list<array<string,mixed>> $pages @return array<string,mixed> */
    private function componentSummary(array $components, array $declaredTotals, array $pages): array
    {
        $grouped = collect($components)->groupBy('label');
        $duplicates = $grouped->filter(fn ($group): bool => $group->count() > 1)->keys()->values()->all();
        $labels = $grouped->keys()->all();
        $allTextKey = $this->normalizer->searchKey(implode("\n", array_column($pages, 'normalized_text')));
        $completeSets = [
            ['investigacion', 'infografia', 'aspectos_formales', 'autoevaluacion', 'coevaluacion', 'metacognicion'],
            ['seccion_i', 'seccion_ii', 'metacognicion'],
        ];
        $complete = collect($completeSets)->contains(fn (array $set): bool => count(array_diff($set, $labels)) === 0)
            || (str_contains($allTextKey, 'resumen de puntajes') && count($labels) >= 2)
            || str_contains($allTextKey, 'desglose detectado');

        if ($duplicates !== [] || ! $complete || $declaredTotals === []) {
            return [
                'computed_total_points' => null,
                'computation_reliability' => 'not_evaluable',
                'computation_reason' => $duplicates !== [] ? 'duplicated_components' : 'incomplete_or_overlapping_breakdown',
                'duplicate_components' => $duplicates,
            ];
        }

        return [
            'computed_total_points' => round((float) $grouped->map(fn ($group): float => (float) $group->first()['value'])->sum(), 2),
            'computation_reliability' => 'exact',
            'computation_reason' => 'complete_named_components',
            'duplicate_components' => [],
        ];
    }

    /** @param list<array<string,mixed>> $items @return list<array<string,mixed>> */
    private function uniqueNumericEvidence(array $items): array
    {
        return collect($items)->unique(fn (array $item): string => $item['value'].'@'.$item['page'].'@'.$item['source_excerpt'])->values()->all();
    }

    /** @return array<string,mixed> */
    private function evidence(mixed $value, int $page, string $excerpt, string $rule, string $reliability): array
    {
        return ['value' => $value, 'page' => $page, 'source_excerpt' => $excerpt, 'rule' => $rule, 'reliability' => $reliability];
    }

    /** @return array<string,mixed> */
    private function numericEvidence(float $value, int $page, string $excerpt, string $rule, string $reliability): array
    {
        return $this->evidence(round($value, 2), $page, $excerpt, $rule, $reliability);
    }

    private function decimal(string|int|float $value): float
    {
        return (float) str_replace(',', '.', (string) $value);
    }
}
