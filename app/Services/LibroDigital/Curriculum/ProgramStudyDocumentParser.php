<?php

namespace App\Services\LibroDigital\Curriculum;

class ProgramStudyDocumentParser extends GenericCurriculumDocumentParser
{
    public function supports(string $documentType): bool
    {
        return $documentType === 'program_study';
    }

    public function parse(array $classification, array $pages): array
    {
        $base = parent::parse($classification, $pages);
        $weeks = $this->estimatedWeeks($pages);
        $hours = $this->estimatedHours($pages);
        $axes = $this->axes($pages);
        $objectives = $this->objectives($pages);
        $skills = $this->skillObjectives($pages);
        $units = $this->units($pages, $hours);
        $candidates = [...$base['candidates'], ...$axes, ...$objectives, ...$skills];
        foreach ($units as $unit) {
            $candidates[] = $unit['candidate'];
            array_push($candidates, ...$unit['children']);
        }

        $summary = [
            'estimated_weeks' => $weeks,
            'estimated_pedagogical_hours' => array_sum($hours),
            'unit_count' => count($units),
            'thematic_objective_count' => count($objectives),
            'skill_objective_count' => count($skills),
            'axis_count' => count($axes),
            'section_count' => count($base['sections']),
            'candidate_count' => count($candidates),
        ];
        $candidates[] = [
            'entity_type' => 'program',
            'candidate_key' => 'program:summary',
            'detected_value' => (string) ($classification['title'] ?? 'Programa de Estudio'),
            'normalized_value' => $this->normalizer->key((string) ($classification['title'] ?? 'Programa de Estudio')),
            'structured_payload' => $summary,
            'confidence' => ($summary['unit_count'] > 0 && $summary['thematic_objective_count'] > 0) ? 0.97 : 0.65,
            'physical_page' => 1,
            'printed_page' => null,
            'source_excerpt' => data_get($pages, '0.normalized_text'),
            'suggested_action' => 'create',
            'warnings' => [],
        ];

        return [
            'sections' => $this->withUnitSections($base['sections'], $units, $pages),
            'candidates' => $candidates,
            'warnings' => array_values(array_filter([
                ...array_diff($base['warnings'], ['generic_parser_used']),
                count($units) === 0 ? 'units_not_detected' : null,
                count($objectives) === 0 ? 'learning_objectives_not_detected' : null,
                collect($units)->flatMap(fn (array $unit): array => $unit['children'])->contains(fn (array $child): bool => $child['suggested_action'] === 'review')
                    ? 'unit_elements_require_human_review'
                    : null,
            ])),
            'summary' => $summary,
        ];
    }

    /** @param list<array<string,mixed>> $pages */
    private function estimatedWeeks(array $pages): ?int
    {
        foreach ($pages as $page) {
            $key = $this->normalizer->key((string) ($page['normalized_text'] ?? ''));
            if (preg_match('/(?:cubren|total)\s+(?:en\s+total\s+)?(\d{1,2})\s+semanas/u', $key, $match) === 1) {
                return (int) $match[1];
            }
        }

        return null;
    }

    /** @param list<array<string,mixed>> $pages @return list<int> */
    private function estimatedHours(array $pages): array
    {
        $hours = [];
        $insideOverview = false;
        foreach ($pages as $page) {
            $text = (string) ($page['normalized_text'] ?? '');
            $key = $this->normalizer->key($text);
            if (str_contains($key, 'se organiza en cuatro unidades') || (str_contains($key, 'vision global del ano') && str_contains($key, 'tiempo estimado'))) {
                $insideOverview = true;
            }
            if (! $insideOverview) {
                continue;
            }
            preg_match_all('/(\d{1,3})\s+horas\s+pedag[oó]gicas/iu', $text, $matches);
            array_push($hours, ...collect($matches[1] ?? [])->map(fn (string $value): int => (int) $value)->values()->all());
            if (count($hours) >= 4 || ($insideOverview && str_contains($key, 'habilidades de investigacion'))) {
                break;
            }
        }

        return array_slice($hours, 0, 4);
    }

    /** @param list<array<string,mixed>> $pages @return list<array<string,mixed>> */
    private function axes(array $pages): array
    {
        $candidates = [];
        $knownAxes = [
            'Ciencias de la vida',
            'Ciencias físicas y químicas',
            'Ciencias de la tierra y el universo',
        ];
        foreach ($pages as $page) {
            $text = (string) ($page['normalized_text'] ?? '');
            $keyText = $this->normalizer->key($text);
            if (! str_contains($keyText, 'ejes') && ! collect($knownAxes)->contains(fn (string $axis): bool => str_contains($keyText, $this->normalizer->key($axis)))) {
                continue;
            }
            foreach ($knownAxes as $axis) {
                if (! str_contains($keyText, $this->normalizer->key($axis))) {
                    continue;
                }
                $key = 'axis:'.hash('sha256', $this->normalizer->key($axis));
                $candidates[$key] = $this->candidate('axis', $key, $axis, $page, ['description' => mb_substr($text, 0, 1200)], 0.96, 'create');
            }
            $lines = array_values(array_filter(array_map('trim', explode("\n", $text))));
            foreach ($lines as $index => $line) {
                $next = $lines[$index + 1] ?? '';
                if (mb_strlen($line) < 5 || mb_strlen($line) > 90 || ! preg_match('/^(?:El eje|En este eje|Este eje)/iu', $next)) {
                    continue;
                }
                $lineKey = $this->normalizer->key($line);
                if (preg_match('/^(?:Programa|Ciencias Naturales|Organizaci[oó]n curricular|B\s*\/)/iu', $line)
                    || str_contains($lineKey, 'basico')
                    || str_contains($lineKey, 'programa de estudio')
                    || mb_strlen(preg_replace('/[^a-z]/', '', $lineKey) ?? '') < 5) {
                    continue;
                }
                $key = 'axis:'.hash('sha256', $this->normalizer->key($line));
                $candidates[$key] = $this->candidate('axis', $key, $line, $page, ['description' => $next], 0.91, 'create');
            }
        }

        return array_values($candidates);
    }

    /** @param list<array<string,mixed>> $pages @return list<array<string,mixed>> */
    private function objectives(array $pages): array
    {
        $candidates = [];
        $inOfficialList = false;
        foreach ($pages as $page) {
            $text = (string) ($page['normalized_text'] ?? '');
            $keyText = $this->normalizer->key($text);
            if (str_contains($keyText, 'listado unico de objetivos de aprendizaje')) {
                $inOfficialList = true;
            }
            if ($inOfficialList && str_contains($keyText, 'vision global del ano')) {
                break;
            }
            if (! $inOfficialList) {
                continue;
            }
            preg_match_all('/\bOA\s*(\d{1,3})(?!\d)/iu', $text, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[1] ?? [] as $index => [$number]) {
                $code = 'OA '.mb_strtoupper((string) $number);
                $key = 'objective:OA:'.mb_strtoupper((string) $number);
                if (isset($candidates[$key])) {
                    continue;
                }
                $matchStart = (int) ($matches[0][$index][1] ?? 0);
                $wordingStart = $matchStart + strlen((string) ($matches[0][$index][0] ?? ''));
                $nextOffset = (int) ($matches[0][$index + 1][1] ?? min(strlen($text), $wordingStart + 1200));
                $excerpt = $this->cleanOfficialWording(substr($text, $wordingStart, max(0, $nextOffset - $wordingStart)));
                $candidates[$key] = $this->candidate('learning_objective', $key, $code, $page, [
                    'official_code' => $code,
                    'objective_type' => 'OA',
                    'official_text' => $excerpt,
                ], 0.92, 'reconcile', [], $excerpt);
            }
        }

        // La tabla de OA suele tener dos columnas y su capa de texto puede
        // intercalar códigos y formulaciones. La "Visión global del año"
        // repite cada OA como texto seguido de su código; esa secuencia es una
        // fuente mucho más estable para conservar la formulación ministerial.
        foreach ($this->objectivesFromAnnualOverview($pages) as $candidate) {
            $candidates[$candidate['candidate_key']] = $candidate;
        }

        uksort($candidates, fn (string $a, string $b): int => strnatcasecmp($a, $b));

        return array_values($candidates);
    }

    /** @param list<array<string,mixed>> $pages @return list<array<string,mixed>> */
    private function objectivesFromAnnualOverview(array $pages): array
    {
        $candidates = [];
        $insideOverview = false;
        foreach ($pages as $page) {
            $text = (string) ($page['normalized_text'] ?? '');
            $keyText = $this->normalizer->key($text);
            if (str_contains($keyText, 'se organiza en cuatro unidades')
                || (str_contains($keyText, 'vision global del ano') && str_contains($keyText, 'tiempo estimado'))) {
                $insideOverview = true;
            }
            if (! $insideOverview || preg_match('/\(OA\s*\d{1,3}\)/iu', $text) !== 1) {
                continue;
            }

            $zone = $text;
            if (preg_match('/\bUnidad\s+\d{1,2}\b/iu', $zone, $unitHeading, PREG_OFFSET_CAPTURE) === 1) {
                $zone = substr($zone, (int) $unitHeading[0][1]);
                $zone = preg_replace('/^Unidad\s+\d{1,2}(?:\s+Unidad\s+\d{1,2})?\s*/iu', '', $zone) ?? $zone;
            }
            preg_match_all('/(?:^|\n|_)\s*(.{20,1600}?)\s*\(OA\s*(\d{1,3})\)\s*/isu', $zone, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $number = (int) $match[2];
                $rawWording = (string) $match[1];
                // En PDFs a dos columnas, el bloque de actitudes de una unidad
                // puede quedar intercalado antes del primer OA de la siguiente.
                // Se conserva solo lo posterior al último encabezado de unidad.
                if (preg_match_all('/\bUnidad\s+\d{1,2}\b/iu', $rawWording, $unitMatches, PREG_OFFSET_CAPTURE) > 0) {
                    $last = collect($unitMatches[0])->last();
                    $rawWording = substr($rawWording, (int) $last[1] + strlen((string) $last[0]));
                }
                $wording = $this->cleanOfficialWording($rawWording);
                if ($number < 1 || mb_strlen($wording) < 20) {
                    continue;
                }
                $code = 'OA '.$number;
                $key = 'objective:OA:'.$number;
                $candidates[$key] = $this->candidate('learning_objective', $key, $code, $page, [
                    'official_code' => $code,
                    'objective_type' => 'OA',
                    'official_text' => $wording,
                    'evidence_section' => 'annual_overview',
                ], 0.98, 'reconcile', [], $wording);
            }
            if (str_contains($keyText, 'vision global del ano')) {
                break;
            }
        }

        return array_values($candidates);
    }

    /** @param list<array<string,mixed>> $pages @return list<array<string,mixed>> */
    private function skillObjectives(array $pages): array
    {
        $candidates = [];
        $inOfficialList = false;
        foreach ($pages as $page) {
            $text = (string) ($page['normalized_text'] ?? '');
            $keyText = $this->normalizer->key($text);
            if (str_contains($keyText, 'listado unico de objetivos de aprendizaje')) {
                $inOfficialList = true;
            }
            if ($inOfficialList && str_contains($keyText, 'vision global del ano')) {
                break;
            }
            if (! $inOfficialList) {
                continue;
            }
            preg_match_all('/(?:^|\n)\s*OA\s*([a-d])\s*(.*?)(?=(?:\n\s*OA\s*(?:[a-d]|\d{1,3}))|\z)/isu', $text, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $letter = mb_strtolower((string) $match[1]);
                $wording = $this->cleanOfficialWording((string) $match[2]);
                $wording = preg_replace('/\s+(?:Ex\s*perimentar|Analizar\s+la\s+evidencia\s+y\s+comunicar|CIENCIAS\s+DE\s+LA\s+VIDA)\s*$/iu', '', $wording) ?? $wording;
                if (mb_strlen($wording) < 15) {
                    continue;
                }
                $key = 'objective:OAH:'.$letter;
                $candidates[$key] = $this->candidate('skill_objective', $key, 'OAH '.$letter, $page, [
                    'official_code' => $letter,
                    'objective_type' => 'OAH',
                    'official_text' => $wording,
                ], 0.88, 'reconcile', [], $wording);
            }
        }

        return array_values($candidates);
    }

    private function cleanOfficialWording(string $wording): string
    {
        $wording = trim($wording, " _\t\n\r\0\x0B");
        // Repara guiones insertados únicamente por un salto/ajuste de línea,
        // preservando los guiones semánticos sin espacios (p. ej. goma-flexible).
        $wording = preg_replace('/(?<=\pL)-\s+(?=\p{Ll})/u', '', $wording) ?? $wording;

        return trim(preg_replace('/\s+/u', ' ', $wording) ?? $wording);
    }

    /** @param list<array<string,mixed>> $pages @param list<int> $hours @return list<array{candidate:array<string,mixed>,children:list<array<string,mixed>>,page_end:int}> */
    private function units(array $pages, array $hours): array
    {
        $summaries = [];
        foreach ($pages as $page) {
            $text = (string) ($page['normalized_text'] ?? '');
            $keyText = $this->normalizer->key($text);
            if (! str_contains($keyText, 'proposito') || (! str_contains($keyText, 'palabras clave') && preg_match('/\bp\s+alabras\s+clave\b/u', $keyText) !== 1)) {
                continue;
            }
            if (preg_match('/Unidad\s+([1-9][0-9]?)/iu', mb_substr($text, 0, 500), $match) !== 1) {
                continue;
            }
            $summaries[] = ['number' => (int) $match[1], 'page' => $page];
        }
        $lastSummaryPage = (int) collect($summaries)->max(fn (array $summary): int => (int) $summary['page']['physical_page_number']);
        $bibliographyPage = collect($pages)->first(function (array $page) use ($lastSummaryPage): bool {
            return (int) $page['physical_page_number'] > $lastSummaryPage
                && str_contains($this->normalizer->key((string) ($page['normalized_text'] ?? '')), 'bibliografia');
        })['physical_page_number'] ?? (int) data_get($pages, array_key_last($pages).'.physical_page_number', 1);
        $result = [];
        foreach ($summaries as $index => $summary) {
            $number = (int) $summary['number'];
            $page = $summary['page'];
            $pageStart = (int) $page['physical_page_number'];
            $pageEnd = isset($summaries[$index + 1]) ? (int) $summaries[$index + 1]['page']['physical_page_number'] - 1 : max($pageStart, (int) $bibliographyPage - 1);
            $text = (string) $page['normalized_text'];
            $blocks = $this->summaryBlocks($text);
            $unitPages = collect($pages)->filter(fn (array $candidatePage): bool => (int) $candidatePage['physical_page_number'] >= $pageStart && (int) $candidatePage['physical_page_number'] <= $pageEnd);
            preg_match_all('/\bOA\s*(\d{1,3})(?!\d)/iu', $unitPages->pluck('normalized_text')->implode("\n"), $unitObjectiveMatches);
            $objectiveCodes = collect($unitObjectiveMatches[1] ?? [])
                ->map(fn (string $code): string => 'OA '.(int) $code)
                ->unique()
                ->sortBy(fn (string $code): int => (int) preg_replace('/\D+/', '', $code))
                ->values()
                ->all();
            $candidateKey = 'unit:'.$number;
            $candidate = $this->candidate('unit', $candidateKey, 'Unidad '.$number, $page, [
                'unit_code' => 'U'.$number,
                'official_order' => $number,
                'semester' => $number <= 2 ? 1 : 2,
                'estimated_pedagogical_hours' => $hours[$index] ?? null,
                'page_start' => $pageStart,
                'page_end' => $pageEnd,
                'purpose' => $blocks['purpose'] ?? null,
                'knowledge' => $blocks['knowledge'] ?? null,
                'prior_knowledge' => $blocks['prior_knowledge'] ?? null,
                'objective_codes' => $objectiveCodes,
            ], 0.97, 'create', [], $text);
            $children = [];
            foreach (['knowledge', 'prior_knowledge', 'skills', 'attitudes'] as $type) {
                foreach ($this->bulletItems((string) ($blocks[$type] ?? '')) as $itemIndex => $item) {
                    $children[] = $this->candidate($type, $candidateKey.':'.$type.':'.hash('sha256', $this->normalizer->key($item)), $item, $page, [
                        'unit_number' => $number,
                        'official_order' => $itemIndex + 1,
                    ], 0.82, 'review', ['extracted_summary_requires_review'], $item, $candidateKey);
                }
            }
            foreach ($this->keywords((string) ($blocks['keywords'] ?? '')) as $itemIndex => $keyword) {
                $children[] = $this->candidate('keyword', $candidateKey.':keyword:'.hash('sha256', $this->normalizer->key($keyword)), $keyword, $page, [
                    'unit_number' => $number,
                    'official_order' => $itemIndex + 1,
                ], 0.90, 'review', ['extracted_summary_requires_review'], $keyword, $candidateKey);
            }
            foreach ($pages as $detailPage) {
                $physical = (int) $detailPage['physical_page_number'];
                if ($physical <= $pageStart || $physical > $pageEnd) {
                    continue;
                }
                $detailText = (string) ($detailPage['normalized_text'] ?? '');
                $detailKey = $this->normalizer->key($detailText);
                if (str_contains($detailKey, 'objetivos de aprendizaje') && str_contains($detailKey, 'indicadores de evaluacion')) {
                    $children[] = $this->candidate('indicator_block', $candidateKey.':indicators:'.$physical, 'Indicadores de evaluación - Unidad '.$number, $detailPage, [
                        'unit_number' => $number,
                        'text' => $detailText,
                    ], 0.78, 'review', ['table_layout_requires_review'], $detailText, $candidateKey);
                }
                if (preg_match('/\bActividades?\s+\d/iu', $detailText)) {
                    $children[] = $this->candidate('activity', $candidateKey.':activity-page:'.$physical, 'Actividades - Unidad '.$number, $detailPage, [
                        'unit_number' => $number,
                        'text' => $detailText,
                    ], 0.76, 'review', ['page_contains_multiple_activities'], $detailText, $candidateKey);
                }
                if (str_contains($detailKey, 'ejemplo de evaluacion') || str_contains($detailKey, 'evaluacion 1')) {
                    $children[] = $this->candidate('suggested_assessment', $candidateKey.':assessment-page:'.$physical, 'Evaluación sugerida - Unidad '.$number, $detailPage, [
                        'unit_number' => $number,
                        'text' => $detailText,
                    ], 0.78, 'review', [], $detailText, $candidateKey);
                }
                if (str_contains($detailKey, 'observaciones al docente')) {
                    $children[] = $this->candidate('teacher_note', $candidateKey.':teacher-note:'.$physical, 'Observaciones al docente - Unidad '.$number, $detailPage, [
                        'unit_number' => $number,
                        'text' => $detailText,
                    ], 0.84, 'review', [], $detailText, $candidateKey);
                }
            }
            $result[] = compact('candidate', 'children', 'pageEnd');
        }

        return $result;
    }

    /** @return array<string,string> */
    private function summaryBlocks(string $text): array
    {
        $definitions = [
            'purpose' => ['PROPÓSITO'],
            'prior_knowledge' => ['CONOCIMIENTOS PREVIOS', 'CONOCIMIENTO PREVIO'],
            'keywords' => ['PALABRAS CLAVE'],
            'knowledge' => ['CONOCIMIENTOS'],
            'skills' => ['HABILIDADES'],
            'attitudes' => ['ACTITUDES'],
        ];
        $positions = [];
        $searchOffset = 0;
        foreach ($definitions as $key => $headings) {
            $best = null;
            foreach ($headings as $heading) {
                if (preg_match('/'.$this->headingPattern($heading).'/iu', $text, $match, PREG_OFFSET_CAPTURE, $searchOffset) === 1) {
                    if ($best === null || $match[0][1] < $best['offset']) {
                        $best = ['offset' => $match[0][1], 'length' => strlen($match[0][0])];
                    }
                }
            }
            if ($best !== null) {
                $positions[$key] = $best;
                $searchOffset = $best['offset'] + $best['length'];
            }
        }
        uasort($positions, fn (array $a, array $b): int => $a['offset'] <=> $b['offset']);
        $keys = array_keys($positions);
        $blocks = [];
        foreach ($keys as $index => $key) {
            $start = $positions[$key]['offset'] + $positions[$key]['length'];
            $end = isset($keys[$index + 1]) ? $positions[$keys[$index + 1]]['offset'] : strlen($text);
            $blocks[$key] = trim(substr($text, $start, max(0, $end - $start)));
        }

        return $blocks;
    }

    private function headingPattern(string $heading): string
    {
        $ascii = str_replace(' ', '', $this->normalizer->key($heading));
        $letters = preg_split('//u', $ascii, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $variants = [
            'a' => '[aáàäâ]', 'e' => '[eéèëê]', 'i' => '[iíìïî]',
            'o' => '[oóòöô]', 'u' => '[uúùüû]', 'n' => '[nñ]',
        ];

        return implode('\\s*', array_map(function (string $letter) use ($variants): string {
            return $variants[mb_strtolower($letter)] ?? preg_quote($letter, '/');
        }, $letters));
    }

    /** @return list<string> */
    private function bulletItems(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }
        $parts = preg_split('/(?:^|\n)\s*[ú•·\-]\s*/u', $text) ?: [];
        if (count($parts) <= 1) {
            $parts = preg_split('/\.\s+(?=\p{Lu})/u', $text) ?: [];
        }

        return collect($parts)->map(fn (string $item): string => trim($item))->filter(fn (string $item): bool => mb_strlen($item) >= 8)->values()->all();
    }

    /** @return list<string> */
    private function keywords(string $text): array
    {
        return collect(preg_split('/[,;\n]+/u', $text) ?: [])
            ->map(fn (string $word): string => trim($word, " .•ú\t\n\r\0\x0B"))
            ->filter(fn (string $word): bool => mb_strlen($word) >= 2 && mb_strlen($word) <= 100)
            ->unique(fn (string $word): string => $this->normalizer->key($word))
            ->values()->all();
    }

    /** @param list<array<string,mixed>> $sections @param list<array{candidate:array<string,mixed>,children:list<array<string,mixed>>,page_end:int}> $units @param list<array<string,mixed>> $pages */
    private function withUnitSections(array $sections, array $units, array $pages): array
    {
        foreach ($units as $unit) {
            $payload = (array) $unit['candidate']['structured_payload'];
            $start = (int) $payload['page_start'];
            $end = (int) $payload['page_end'];
            $sections[] = [
                'section_type' => 'unit',
                'heading' => $unit['candidate']['detected_value'],
                'normalized_heading' => $unit['candidate']['normalized_value'],
                'official_order' => 100 + (int) $payload['official_order'],
                'page_start' => $start,
                'page_end' => $end,
                'full_text' => collect($pages)->filter(fn (array $page): bool => (int) $page['physical_page_number'] >= $start && (int) $page['physical_page_number'] <= $end)->pluck('normalized_text')->implode("\n\n"),
                'structured_data' => $payload,
                'confidence' => 0.97,
                'review_status' => 'pending',
            ];
        }

        return collect($sections)->sortBy(['page_start', 'official_order'])->values()->all();
    }

    /** @param array<string,mixed> $page @param array<string,mixed> $payload @param list<string> $warnings */
    private function candidate(string $type, string $key, string $value, array $page, array $payload, float $confidence, string $action, array $warnings = [], ?string $excerpt = null, ?string $parentKey = null): array
    {
        return [
            'entity_type' => $type,
            'candidate_key' => $key,
            'parent_key' => $parentKey,
            'detected_value' => trim($value),
            'normalized_value' => $this->normalizer->key($value),
            'structured_payload' => $payload,
            'confidence' => $confidence,
            'physical_page' => (int) ($page['physical_page_number'] ?? 1),
            'printed_page' => $page['printed_page_label'] ?? null,
            'source_excerpt' => mb_substr(trim($excerpt ?? (string) ($page['normalized_text'] ?? '')), 0, 1500),
            'suggested_action' => $action,
            'warnings' => $warnings,
        ];
    }
}
