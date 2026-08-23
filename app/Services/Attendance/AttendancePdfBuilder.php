<?php

namespace App\Services\Attendance;

class AttendancePdfBuilder
{
    private const PAGE_WIDTH = 842.0;

    private const PAGE_HEIGHT = 595.0;

    private const MARGIN = 32.0;

    private const CONTENT_WIDTH = 778.0;

    private const CONTENT_BOTTOM = 45.0;

    private array $pages = [];

    private int $pageIndex = -1;

    private float $cursorY = 0;

    private string $title = '';

    private string $footerContext = '';

    private string $organizationName = 'CNSC GESTIÓN';

    private string $reportTrace = '';

    private string $sourceLabel = 'registros de asistencia del sistema';

    private string $watermark = '';

    private string $reportLabel = 'REPORTE INSTITUCIONAL';

    public function build(string $title, array $metadata, array $sections, array $dashboard = []): string
    {
        $this->pages = [];
        $this->pageIndex = -1;
        $this->title = $title;
        $this->footerContext = $this->cell($metadata['periodo'] ?? '');
        $branding = is_array($dashboard['branding'] ?? null) ? $dashboard['branding'] : [];
        $this->organizationName = $this->cell($branding['organization_name'] ?? 'CNSC GESTIÓN');
        $this->reportTrace = $this->cell($branding['report_trace'] ?? '');
        $this->sourceLabel = $this->cell($branding['source_label'] ?? 'registros de asistencia del sistema');
        $this->watermark = $this->cell($branding['watermark'] ?? '');
        $this->reportLabel = mb_strtoupper($this->cell(
            $branding['report_label'] ?? $metadata['tipo de reporte'] ?? 'Reporte institucional'
        ));
        $this->startPage(true);
        $this->renderMetadata($metadata);

        $summary = collect($sections)->first(fn (array $section) => ($section['title'] ?? '') === 'Resumen ejecutivo');
        if ($summary) {
            $this->renderSummary($summary);
        }
        if (! empty($dashboard['monthly'])) {
            $this->renderMonthlyTrend($dashboard['monthly'], $dashboard['summary']['target_rate'] ?? null);
        }

        foreach ($sections as $section) {
            $sectionTitle = (string) ($section['title'] ?? 'Sección');
            if ($sectionTitle === 'Resumen ejecutivo') {
                continue;
            }
            if (($section['layout'] ?? null) === 'curriculum_program_summary') {
                $this->renderCurriculumProgramSummary($section);

                continue;
            }
            if (($section['layout'] ?? null) === 'curriculum_program_dashboard') {
                $this->renderCurriculumProgramDashboard($section);

                continue;
            }
            if (($section['layout'] ?? null) === 'curriculum_objectives') {
                $this->renderCurriculumObjectives($section);

                continue;
            }
            if (($section['layout'] ?? null) === 'curriculum_unit_detail') {
                $this->renderCurriculumUnitDetail($section);

                continue;
            }
            if (($section['layout'] ?? null) === 'curriculum_sources') {
                $this->renderCurriculumSources($section);

                continue;
            }
            if ($sectionTitle === 'Cursos') {
                $this->renderCourseChart($section);
                $section['title'] = 'Detalle por curso';
            }
            $this->renderTable($section);
        }

        if (count($sections) === 0) {
            $this->renderEmptyState('No hay datos disponibles para los filtros aplicados.');
        }

        $this->appendFooters();

        return $this->document();
    }

    private function renderCurriculumProgramDashboard(array $section): void
    {
        $this->ensureSpace(235);
        $this->sectionHeading('Distribución curricular', (string) ($section['subtitle'] ?? 'Cobertura del programa curricular'));
        $top = $this->cursorY;
        $panelWidth = (self::CONTENT_WIDTH - 12) / 2;
        $this->curriculumBars(self::MARGIN, $top, $panelWidth, 'Horas por unidad', (array) ($section['hours'] ?? []), [0.18, 0.51, 0.77]);
        $this->curriculumBars(self::MARGIN + $panelWidth + 12, $top, $panelWidth, 'OA por eje', (array) ($section['axes'] ?? []), [0.16, 0.55, 0.40]);
        $this->cursorY = $top - 190;
    }

    private function renderCurriculumProgramSummary(array $section): void
    {
        $this->ensureSpace(230);
        $this->sectionHeading((string) ($section['title'] ?? 'Síntesis curricular'), implode(' · ', array_filter([
            $this->cell($section['subtitle'] ?? ''),
            $this->cell($section['status'] ?? ''),
        ])));

        $metrics = array_slice(array_values((array) ($section['metrics'] ?? [])), 0, 6);
        $colors = [
            [0.25, 0.32, 0.54], [0.18, 0.51, 0.77], [0.16, 0.55, 0.40],
            [0.48, 0.38, 0.66], [0.83, 0.57, 0.13], [0.18, 0.45, 0.55],
        ];
        $gap = 8.0;
        $width = (self::CONTENT_WIDTH - ($gap * 2)) / 3;
        $height = 52.0;
        $top = $this->cursorY;
        foreach ($metrics as $index => $metric) {
            $metric = (array) $metric;
            $row = intdiv($index, 3);
            $column = $index % 3;
            $bottom = $top - (($height + $gap) * $row) - $height;
            $x = self::MARGIN + (($width + $gap) * $column);
            $color = $colors[$index] ?? $colors[0];
            $this->fillRect($x, $bottom, $width, $height, [0.975, 0.98, 0.988], [0.87, 0.90, 0.94]);
            $this->fillRect($x, $bottom, 4, $height, $color);
            $this->text($x + 13, $bottom + 35, $metric['label'] ?? '-', 6.5, 'F2', [0.43, 0.48, 0.56], 28);
            $this->text($x + 13, $bottom + 16, $metric['value'] ?? '-', 14, 'F2', $color, 14);
            $this->text($x + 66, $bottom + 17, $metric['detail'] ?? '', 6.5, 'F1', [0.43, 0.48, 0.56], 31);
        }
        $rows = max(1, (int) ceil(count($metrics) / 3));
        $this->cursorY = $top - (($height + $gap) * $rows) - 2;

        $description = $this->cell($section['description'] ?? '');
        if ($description !== '') {
            $this->renderCurriculumText('ALCANCE DEL PROGRAMA', $description, 7.5, [0.24, 0.29, 0.36], 150);
        }
        $source = $this->cell($section['source'] ?? '');
        if ($source !== '') {
            $this->renderCurriculumText('RESPALDO MINISTERIAL', $source, 7, [0.33, 0.39, 0.47], 150);
        }
    }

    private function curriculumBars(float $x, float $top, float $width, string $title, array $dataset, array $color): void
    {
        $labels = array_values((array) ($dataset['labels'] ?? []));
        $series = array_values((array) ($dataset['series'] ?? []));
        $height = 176.0;
        $this->fillRect($x, $top - $height, $width, $height, [0.985, 0.988, 0.994], [0.88, 0.90, 0.93]);
        $this->text($x + 12, $top - 20, $title, 8.5, 'F2', [0.16, 0.20, 0.27], 42);
        $maximum = max(1.0, (float) max([1, ...array_map(fn ($value): float => (float) $value, $series)]));
        $count = max(1, min(6, count($labels)));
        $barTop = $top - 43;
        foreach (array_slice($labels, 0, 6) as $index => $label) {
            $value = (float) ($series[$index] ?? 0);
            $y = $barTop - ($index * (116 / $count));
            $this->text($x + 12, $y, $this->cell($label), 6.1, 'F1', [0.38, 0.43, 0.51], 34);
            $barX = $x + 158;
            $barWidth = max(2.0, ($width - 200) * ($value / $maximum));
            $this->fillRect($barX, $y - 1, $width - 200, 8, [0.91, 0.93, 0.95]);
            $this->fillRect($barX, $y - 1, $barWidth, 8, $color);
            $this->text($x + $width - 33, $y, number_format($value, 0, ',', '.'), 6.5, 'F2', $color, 8);
        }
        if ($labels === []) {
            $this->text($x + 12, $top - 65, 'Sin datos informados', 7, 'F1', [0.48, 0.52, 0.59]);
        }
    }

    private function startPage(bool $first = false): void
    {
        $this->pages[] = [];
        $this->pageIndex = count($this->pages) - 1;
        $this->fillRect(0, 0, self::PAGE_WIDTH, self::PAGE_HEIGHT, [1, 1, 1]);
        if ($this->watermark !== '') {
            // Visible on every page without relying on client-side PDF tooling.
            $this->text(120, 300, mb_strtoupper($this->watermark), 24, 'F2', [0.90, 0.91, 0.93], 48);
        }

        if ($first) {
            $this->fillRect(0, 522, self::PAGE_WIDTH, 73, [0.12, 0.17, 0.28]);
            $this->fillRect(0, 519, self::PAGE_WIDTH, 3, [0.16, 0.55, 0.40]);
            $this->text(32, 570, $this->organizationName, 8, 'F2', [0.72, 0.82, 0.94], 72);
            $this->text(32, 541, $this->title, 20, 'F2', [1, 1, 1], 86);
            $this->text(675, 570, $this->reportLabel, 7, 'F2', [0.72, 0.82, 0.94], 32);
            $this->cursorY = 505;

            return;
        }

        $this->fillRect(0, 554, self::PAGE_WIDTH, 41, [0.12, 0.17, 0.28]);
        $this->fillRect(0, 551, self::PAGE_WIDTH, 3, [0.16, 0.55, 0.40]);
        $this->text(32, 579, $this->organizationName, 7.5, 'F2', [0.72, 0.82, 0.94], 76);
        $this->text(32, 560, $this->title, 10.5, 'F2', [1, 1, 1], 100);
        $this->cursorY = 532;
    }

    private function renderMetadata(array $metadata): void
    {
        $cards = [
            ['Periodo', $metadata['periodo'] ?? '-'],
            ['Año académico', $metadata['año académico'] ?? '-'],
            ['Tipo de reporte', $metadata['tipo de reporte'] ?? 'Reporte de asistencia'],
            ['Generado', ($metadata['fecha'] ?? '-').' · '.($metadata['generado por'] ?? '-')],
        ];
        $gap = 8.0;
        $availableWidth = self::CONTENT_WIDTH - ($gap * 3);
        $widths = [
            $availableWidth * 0.20,
            $availableWidth * 0.14,
            $availableWidth * 0.28,
            $availableWidth * 0.38,
        ];
        $height = 42.0;
        $bottom = $this->cursorY - $height;
        $x = self::MARGIN;

        foreach ($cards as $index => [$label, $value]) {
            $width = $widths[$index];
            $this->fillRect($x, $bottom, $width, $height, [0.96, 0.97, 0.985], [0.86, 0.89, 0.93]);
            $this->fillRect($x, $bottom, 3, $height, [0.25, 0.32, 0.54]);
            $maximumCharacters = max(12, (int) floor(($width - 22) / 4.8));
            $this->text($x + 11, $bottom + 27, $label, 6.5, 'F2', [0.43, 0.48, 0.56], $maximumCharacters);
            $this->text($x + 11, $bottom + 11, $this->cell($value), 8.5, 'F2', [0.16, 0.20, 0.27], $maximumCharacters);
            $x += $width + $gap;
        }

        $this->cursorY = $bottom - 12;
        $filters = $this->cell($metadata['filtros'] ?? 'Sin filtros adicionales');
        $this->text(self::MARGIN, $this->cursorY, 'FILTROS APLICADOS', 6.5, 'F2', [0.43, 0.48, 0.56]);
        $this->text(self::MARGIN + 88, $this->cursorY, $filters !== '' ? $filters : 'Sin filtros adicionales', 7, 'F1', [0.32, 0.37, 0.45], 155);
        $this->cursorY -= 21;
    }

    private function renderSummary(array $section): void
    {
        $values = collect($section['rows'] ?? [])->mapWithKeys(fn (array $row) => [(string) ($row[0] ?? '') => $row[1] ?? '-'])->all();
        $this->sectionHeading('Resumen ejecutivo', 'Indicadores principales del periodo seleccionado');

        $cards = isset($values['Días lectivos registrados'])
            ? [
                ['Asistencia', $values['Asistencia'] ?? '-', [0.16, 0.55, 0.40]],
                ['Días lectivos registrados', $values['Días lectivos registrados'], [0.25, 0.32, 0.54]],
                ['Días presentes', $values['Días presentes'] ?? 0, [0.18, 0.51, 0.77]],
                ['Días perdidos', $values['Días perdidos'] ?? 0, [0.76, 0.24, 0.29]],
                ['Ausencias injustificadas', $values['Ausencias injustificadas'] ?? 0, [0.83, 0.57, 0.13]],
                ['Atrasos', $values['Atrasos'] ?? 0, [0.48, 0.38, 0.66]],
            ]
            : [
                ['Asistencia', $values['Asistencia'] ?? '-', [0.16, 0.55, 0.40]],
                ['Meta institucional', $values['Meta'] ?? '-', [0.25, 0.32, 0.54]],
                ['Presentes', $values['Presentes'] ?? 0, [0.18, 0.51, 0.77]],
                ['Ausentes', $values['Ausentes'] ?? 0, [0.76, 0.24, 0.29]],
                ['Estudiantes en riesgo', $values['Estudiantes en riesgo'] ?? 0, [0.83, 0.57, 0.13]],
                ['Alertas abiertas', $values['Alertas abiertas'] ?? 0, [0.48, 0.38, 0.66]],
            ];
        $gap = 8.0;
        $width = (self::CONTENT_WIDTH - ($gap * 2)) / 3;
        $height = 47.0;
        $top = $this->cursorY;

        foreach ($cards as $index => [$label, $value, $color]) {
            $row = intdiv($index, 3);
            $column = $index % 3;
            $bottom = $top - (($height + $gap) * $row) - $height;
            $x = self::MARGIN + (($width + $gap) * $column);
            $this->fillRect($x, $bottom, $width, $height, [0.975, 0.98, 0.988], [0.88, 0.90, 0.93]);
            $this->fillRect($x, $bottom, 4, $height, $color);
            $this->text($x + 13, $bottom + 31, $label, 7, 'F2', [0.43, 0.48, 0.56], 35);
            $this->text($x + 13, $bottom + 11, $this->cell($value), 14, 'F2', [0.14, 0.18, 0.24], 22);
        }
        $this->cursorY = $top - (($height + $gap) * 2) - 4;

        $this->renderCompositionBar($values);
    }

    private function renderCompositionBar(array $values): void
    {
        $this->ensureSpace(76);
        $present = $this->numeric($values['Presentes'] ?? $values['Días presentes'] ?? 0);
        $absent = $this->numeric($values['Ausentes'] ?? $values['Días perdidos'] ?? 0);
        $justified = min($absent, $this->numeric($values['Justificadas'] ?? $values['Ausencias justificadas'] ?? 0));
        $unjustified = min(max(0, $absent - $justified), $this->numeric($values['Injustificadas'] ?? $values['Ausencias injustificadas'] ?? 0));
        $otherAbsent = max(0, $absent - $justified - $unjustified);
        $segments = [
            ['Presentes', $present, [0.16, 0.55, 0.40]],
            ['Justificadas', $justified, [0.18, 0.51, 0.77]],
            ['Injustificadas', $unjustified + $otherAbsent, [0.76, 0.24, 0.29]],
        ];
        $total = max(1.0, array_sum(array_column($segments, 1)));

        $this->text(self::MARGIN, $this->cursorY, 'Composición de registros', 9, 'F2', [0.16, 0.20, 0.27]);
        $barY = $this->cursorY - 25;
        $this->fillRect(self::MARGIN, $barY, self::CONTENT_WIDTH, 16, [0.91, 0.93, 0.95]);
        $x = self::MARGIN;
        foreach ($segments as [$label, $value, $color]) {
            $width = self::CONTENT_WIDTH * ($value / $total);
            if ($width > 0) {
                $this->fillRect($x, $barY, $width, 16, $color);
                $x += $width;
            }
        }

        $legendX = self::MARGIN;
        foreach ($segments as [$label, $value, $color]) {
            $this->fillRect($legendX, $barY - 20, 7, 7, $color);
            $rate = ($value / $total) * 100;
            $legend = $label.': '.number_format($value, 0, ',', '.').' ('.number_format($rate, 1, ',', '.').' %)';
            $this->text($legendX + 11, $barY - 19, $legend, 7, 'F1', [0.36, 0.41, 0.48], 34);
            $legendX += 210;
        }
        $this->cursorY = $barY - 32;
    }

    private function renderMonthlyTrend(array $rows, mixed $target): void
    {
        $rows = collect($rows)->filter(fn (array $row) => isset($row['attendance_rate']))->values()->all();
        if ($rows === []) {
            return;
        }

        $this->ensureSpace(150);
        $this->sectionHeading('Evolución mensual', 'Tasa de asistencia y referencia de meta institucional');
        $top = $this->cursorY;
        $height = 112.0;
        $bottom = $top - $height;
        $this->fillRect(self::MARGIN, $bottom, self::CONTENT_WIDTH, $height, [0.985, 0.988, 0.994], [0.88, 0.90, 0.93]);
        $chartX = self::MARGIN + 42;
        $chartY = $bottom + 25;
        $chartWidth = self::CONTENT_WIDTH - 68;
        $chartHeight = 68.0;

        foreach ([0, 25, 50, 75, 100] as $tick) {
            $y = $chartY + (($tick / 100) * $chartHeight);
            $this->line($chartX, $y, $chartX + $chartWidth, $y, [0.86, 0.89, 0.93], 0.6);
            $this->text(self::MARGIN + 10, $y - 2, $tick.' %', 6, 'F1', [0.48, 0.52, 0.59]);
        }

        $targetValue = is_numeric($target) ? max(0, min(100, (float) $target)) : null;
        if ($targetValue !== null) {
            $targetY = $chartY + (($targetValue / 100) * $chartHeight);
            $this->line($chartX, $targetY, $chartX + $chartWidth, $targetY, [0.83, 0.57, 0.13], 1, [4, 3]);
            $this->text($chartX + $chartWidth - 52, $targetY + 4, 'Meta '.number_format($targetValue, 1, ',', '.').' %', 6, 'F2', [0.63, 0.42, 0.08]);
        }

        $points = [];
        $count = count($rows);
        foreach ($rows as $index => $row) {
            $x = $count > 1 ? $chartX + (($chartWidth / ($count - 1)) * $index) : $chartX + ($chartWidth / 2);
            $rate = max(0, min(100, (float) $row['attendance_rate']));
            $y = $chartY + (($rate / 100) * $chartHeight);
            $points[] = [$x, $y, $rate];
            $label = $this->cell($row['label'] ?? $row['key'] ?? '');
            $this->text($x - 18, $bottom + 10, $label, 6, 'F1', [0.43, 0.48, 0.56], 9);
        }
        if (count($points) > 1) {
            $path = [];
            foreach ($points as $index => [$x, $y]) {
                $path[] = $this->number($x).' '.$this->number($y).' '.($index === 0 ? 'm' : 'l');
            }
            $this->add($this->strokeColor([0.25, 0.32, 0.54]).' 2 w '.implode(' ', $path).' S');
        }
        foreach ($points as [$x, $y, $rate]) {
            $this->fillRect($x - 2.5, $y - 2.5, 5, 5, [0.25, 0.32, 0.54], [1, 1, 1]);
            $this->text($x - 13, min($chartY + $chartHeight - 7, $y + 8), number_format($rate, 1, ',', '.').' %', 6, 'F2', [0.25, 0.32, 0.54]);
        }
        $this->cursorY = $bottom - 13;
    }

    private function renderCourseChart(array $section): void
    {
        $headers = array_values($section['headers'] ?? []);
        $nameIndex = array_search('Curso', $headers, true);
        $rateIndex = array_search('Asistencia', $headers, true);
        if ($nameIndex === false || $rateIndex === false) {
            return;
        }
        $rows = collect($section['rows'] ?? [])->map(fn (array $row) => [
            'name' => $this->cell($row[$nameIndex] ?? '-'),
            'rate' => $this->numeric($row[$rateIndex] ?? 0),
        ])->sortBy('rate')->take(8)->values()->all();
        if ($rows === []) {
            return;
        }

        $height = 58 + (count($rows) * 17);
        $this->ensureSpace($height + 34);
        $this->sectionHeading('Comparativo por curso', 'Cursos con menor asistencia dentro del alcance seleccionado');
        $top = $this->cursorY;
        $bottom = $top - $height;
        $this->fillRect(self::MARGIN, $bottom, self::CONTENT_WIDTH, $height, [0.985, 0.988, 0.994], [0.88, 0.90, 0.93]);
        $chartX = self::MARGIN + 170;
        $chartWidth = self::CONTENT_WIDTH - 230;
        $chartTop = $top - 24;

        foreach ([0, 25, 50, 75, 100] as $tick) {
            $x = $chartX + (($tick / 100) * $chartWidth);
            $this->line($x, $bottom + 20, $x, $chartTop + 5, [0.88, 0.90, 0.93], 0.5);
            $this->text($x - 7, $bottom + 8, (string) $tick, 6, 'F1', [0.48, 0.52, 0.59]);
        }

        foreach ($rows as $index => $row) {
            $y = $chartTop - ($index * 17);
            $rate = max(0, min(100, (float) $row['rate']));
            $color = $rate < 85 ? [0.76, 0.24, 0.29] : ($rate < 90 ? [0.83, 0.57, 0.13] : [0.16, 0.55, 0.40]);
            $this->text(self::MARGIN + 12, $y, $row['name'], 7, 'F2', [0.27, 0.32, 0.39], 29);
            $this->fillRect($chartX, $y - 2, $chartWidth, 7, [0.91, 0.93, 0.95]);
            $this->fillRect($chartX, $y - 2, $chartWidth * ($rate / 100), 7, $color);
            $this->text($chartX + $chartWidth + 8, $y, number_format($rate, 1, ',', '.').' %', 7, 'F2', $color);
        }
        $this->cursorY = $bottom - 14;
    }

    /**
     * Dedicated long-form curriculum layout. Official descriptions and indicators
     * are wrapped across as many lines/pages as required; no ellipsis is applied.
     */
    private function renderCurriculumObjectives(array $section): void
    {
        $rows = array_values((array) ($section['rows'] ?? []));
        if ($rows === []) {
            $this->renderEmptyState('No se encontraron objetivos para los filtros aplicados.');

            return;
        }

        $this->ensureSpace(120);
        $this->sectionHeading((string) ($section['title'] ?? 'Objetivos curriculares'), 'Texto oficial y trazabilidad de fuentes');
        $currentGroup = null;
        foreach ($rows as $row) {
            $row = (array) $row;
            $group = $this->cell($row['group'] ?? 'Sin clasificación');
            if ($group !== $currentGroup) {
                $this->ensureSpace(40);
                $this->fillRect(self::MARGIN, $this->cursorY - 28, self::CONTENT_WIDTH, 28, [0.91, 0.94, 0.98], [0.80, 0.85, 0.92]);
                $this->text(self::MARGIN + 12, $this->cursorY - 18, mb_strtoupper($group), 8.5, 'F2', [0.20, 0.27, 0.43]);
                $this->cursorY -= 39;
                $currentGroup = $group;
            }

            $this->renderCurriculumObjective($row);
        }
    }

    private function renderCurriculumUnitDetail(array $section): void
    {
        $blocks = [
            ['PROPÓSITO', array_filter([$this->cell($section['purpose'] ?? '')])],
            ['OBJETIVOS DE APRENDIZAJE', (array) ($section['objectives'] ?? [])],
            ['CONOCIMIENTOS PREVIOS', (array) ($section['prior_knowledge'] ?? [])],
            ['CONOCIMIENTOS', (array) ($section['knowledge'] ?? [])],
            ['HABILIDADES', (array) ($section['skills'] ?? [])],
            ['ACTITUDES', (array) ($section['attitudes'] ?? [])],
        ];
        $estimatedHeight = 62;
        foreach ($blocks as [, $values]) {
            if ($values === []) {
                continue;
            }
            $estimatedHeight += 17 + collect($values)->sum(fn ($value): int => count($this->wrapComplete('• '.$this->cell($value), 145)) * 10 + 3);
        }
        $estimatedHeight += 34;
        $this->ensureSpace(min(455, $estimatedHeight));

        $subtitle = implode(' · ', array_filter([
            filled($section['semester'] ?? null) ? 'Semestre '.$section['semester'] : null,
            filled($section['hours'] ?? null) ? $section['hours'].' horas pedagógicas' : null,
            ($section['page_range'] ?? 'Sin localizar') !== 'Sin localizar' ? 'páginas '.$section['page_range'] : null,
        ]));
        $this->sectionHeading($this->cell($section['title'] ?? 'Unidad curricular'), $subtitle);
        foreach ($blocks as [$label, $values]) {
            $values = array_values(array_filter(array_map(fn ($value): string => $this->cell($value), $values)));
            if ($values === []) {
                continue;
            }
            $this->renderCurriculumLabel($label);
            foreach ($values as $value) {
                $this->renderCurriculumText('', '• '.$value, 7.1, [0.27, 0.32, 0.40], 145);
            }
        }

        $keywords = array_values(array_filter(array_map(fn ($value): string => $this->cell($value), (array) ($section['keywords'] ?? []))));
        if ($keywords !== []) {
            $this->ensureSpace(32);
            $this->fillRect(self::MARGIN, $this->cursorY - 25, self::CONTENT_WIDTH, 25, [0.93, 0.97, 0.96], [0.80, 0.89, 0.86]);
            $this->text(self::MARGIN + 11, $this->cursorY - 16, 'PALABRAS CLAVE', 6.2, 'F2', [0.16, 0.45, 0.39]);
            $this->text(self::MARGIN + 105, $this->cursorY - 16, implode(', ', $keywords), 7, 'F1', [0.27, 0.36, 0.34], 135);
            $this->cursorY -= 37;
        }
    }

    private function renderCurriculumSources(array $section): void
    {
        $rows = array_values((array) ($section['rows'] ?? []));
        $this->ensureSpace(145);
        $this->sectionHeading((string) ($section['title'] ?? 'Fuentes ministeriales'), count($rows).' documento(s) de respaldo');
        foreach ($rows as $row) {
            $row = (array) $row;
            $this->ensureSpace(125);
            $this->fillRect(self::MARGIN, $this->cursorY - 30, self::CONTENT_WIDTH, 30, [0.975, 0.98, 0.99], [0.86, 0.89, 0.93]);
            $this->fillRect(self::MARGIN, $this->cursorY - 30, 4, 30, [0.16, 0.55, 0.40]);
            $this->text(self::MARGIN + 13, $this->cursorY - 19, $row['title'] ?? 'Documento ministerial', 9, 'F2', [0.16, 0.20, 0.27], 116);
            $this->cursorY -= 40;
            $details = implode(' · ', array_filter([
                $this->cell($row['edition'] ?? ''),
                $this->cell($row['decree'] ?? ''),
                filled($row['page_count'] ?? null) ? $row['page_count'].' páginas' : null,
            ]));
            $this->renderCurriculumText('IDENTIFICACIÓN', $details, 7.1, [0.31, 0.36, 0.44], 145);
            $this->renderCurriculumText('SHA-256', $this->cell($row['sha256'] ?? '-'), 6.8, [0.31, 0.36, 0.44], 106);
            if (filled($row['official_url'] ?? null)) {
                $this->renderCurriculumText('REFERENCIA OFICIAL', $this->cell($row['official_url']), 6.8, [0.25, 0.32, 0.54], 118);
            }
        }
    }

    /** @param array<string, mixed> $row */
    private function renderCurriculumObjective(array $row): void
    {
        $code = $this->cell($row['code'] ?? '-');
        $description = $this->cell($row['description'] ?? '-');
        $indicators = $this->cell($row['indicators'] ?? '');
        $sources = array_values((array) ($row['sources'] ?? []));
        $estimatedHeight = 74
            + (count($this->wrapComplete($description, 154)) * 10)
            + ($indicators !== '' ? 15 + (count($this->wrapComplete($indicators, 154)) * 10) : 0)
            + collect($sources)->sum(fn ($source): int => 14 + (count($this->wrapComplete('• '.$this->cell($source), 143)) * 10));
        // Evita encabezados o una única línea huérfana en la página siguiente.
        // Los OA excepcionalmente extensos siguen pudiendo paginarse dentro de
        // renderCurriculumText, sin truncar contenido.
        $this->ensureSpace(min(460, $estimatedHeight));
        $badges = implode(' · ', array_filter([
            $this->cell($row['type'] ?? ''),
            $this->cell($row['status'] ?? ''),
            $this->cell($row['catalog'] ?? ''),
        ]));
        $this->fillRect(self::MARGIN, $this->cursorY - 29, self::CONTENT_WIDTH, 29, [0.975, 0.98, 0.99], [0.86, 0.89, 0.93]);
        $this->fillRect(self::MARGIN, $this->cursorY - 29, 4, 29, [0.25, 0.32, 0.54]);
        $this->text(self::MARGIN + 13, $this->cursorY - 18, $code, 10, 'F2', [0.14, 0.18, 0.24]);
        $this->text(self::MARGIN + 180, $this->cursorY - 18, $badges, 7, 'F1', [0.40, 0.45, 0.53]);
        $this->cursorY -= 39;

        $scope = implode(' · ', array_filter([
            filled($row['axis'] ?? null) ? 'Eje: '.$this->cell($row['axis']) : null,
            filled($row['unit'] ?? null) ? 'Unidad: '.$this->cell($row['unit']) : null,
            filled($row['source_page'] ?? null) ? 'Ubicación en fuente: '.$this->cell($row['source_page']) : null,
        ]));
        if ($scope !== '') {
            $this->renderCurriculumText('', $scope, 7, [0.43, 0.48, 0.56]);
        }
        $this->renderCurriculumText('DESCRIPCIÓN OFICIAL', $description, 7.7, [0.18, 0.22, 0.29]);

        if ($indicators !== '') {
            $this->renderCurriculumText('INDICADORES', $indicators, 7.2, [0.31, 0.36, 0.44]);
        }

        if ($sources !== []) {
            $this->renderCurriculumLabel('FUENTES Y TRAZABILIDAD');
            foreach ($sources as $source) {
                $this->renderCurriculumText('', '• '.$this->cell($source), 6.8, [0.37, 0.42, 0.50], 143);
            }
        }

        $this->ensureSpace(15);
        $this->line(self::MARGIN, $this->cursorY - 3, self::MARGIN + self::CONTENT_WIDTH, $this->cursorY - 3, [0.88, 0.90, 0.93], 0.5);
        $this->cursorY -= 15;
    }

    private function renderCurriculumLabel(string $label): void
    {
        $this->ensureSpace(15);
        $this->text(self::MARGIN + 8, $this->cursorY, $label, 6.3, 'F2', [0.43, 0.48, 0.56]);
        $this->cursorY -= 11;
    }

    private function renderCurriculumText(string $label, string $value, float $size, array $color, int $maximumCharacters = 154): void
    {
        if ($label !== '') {
            $this->renderCurriculumLabel($label);
        }
        foreach ($this->wrapComplete($value, $maximumCharacters) as $line) {
            $this->ensureSpace(12);
            $this->text(self::MARGIN + 8, $this->cursorY, $line, $size, 'F1', $color);
            $this->cursorY -= 10;
        }
        $this->cursorY -= 4;
    }

    /** @return list<string> */
    private function wrapComplete(string $value, int $maximumCharacters): array
    {
        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
        if ($value === '') {
            return ['-'];
        }

        $result = [];
        foreach (explode("\n", $value) as $paragraph) {
            $paragraph = trim((string) preg_replace('/\s+/u', ' ', $paragraph));
            if ($paragraph === '') {
                $result[] = '';

                continue;
            }
            $words = preg_split('/\s+/u', $paragraph) ?: [$paragraph];
            $line = '';
            foreach ($words as $word) {
                while (mb_strlen($word) > $maximumCharacters) {
                    if ($line !== '') {
                        $result[] = $line;
                        $line = '';
                    }
                    $result[] = mb_substr($word, 0, $maximumCharacters);
                    $word = mb_substr($word, $maximumCharacters);
                }
                $candidate = $line === '' ? $word : $line.' '.$word;
                if (mb_strlen($candidate) <= $maximumCharacters) {
                    $line = $candidate;

                    continue;
                }
                $result[] = $line;
                $line = $word;
            }
            if ($line !== '') {
                $result[] = $line;
            }
        }

        return $result ?: ['-'];
    }

    private function renderTable(array $section): void
    {
        $title = (string) ($section['title'] ?? 'Detalle');
        $headers = array_values($section['headers'] ?? []);
        $rows = array_values($section['rows'] ?? []);

        $this->ensureSpace(82);
        $this->sectionHeading($title, count($rows).' registros');
        if ($headers === []) {
            $this->renderEmptyState('Esta sección no contiene columnas exportables.');

            return;
        }
        if ($rows === []) {
            $this->renderEmptyState('No hay registros para esta sección.');

            return;
        }

        $widths = $this->tableWidths($headers, $rows);
        $this->drawTableHeader($headers, $widths);
        foreach ($rows as $index => $row) {
            [$lines, $height] = $this->tableRowLayout(array_values((array) $row), $widths);
            if ($this->cursorY - $height < self::CONTENT_BOTTOM) {
                $this->startPage();
                $this->sectionHeading($title.' · continuación', count($rows).' registros totales');
                $this->drawTableHeader($headers, $widths);
            }
            $this->drawTableRow($lines, $widths, $height, $index % 2 === 1);
        }
        $this->cursorY -= 12;
    }

    private function drawTableHeader(array $headers, array $widths): void
    {
        $height = 25.0;
        $bottom = $this->cursorY - $height;
        $this->fillRect(self::MARGIN, $bottom, self::CONTENT_WIDTH, $height, [0.25, 0.32, 0.54]);
        $x = self::MARGIN;
        foreach ($headers as $index => $header) {
            $this->text($x + 5, $bottom + 9, $this->cell($header), 7, 'F2', [1, 1, 1], max(4, (int) floor($widths[$index] / 4.4)));
            $x += $widths[$index];
        }
        $this->cursorY = $bottom;
    }

    private function drawTableRow(array $lines, array $widths, float $height, bool $alternate): void
    {
        $bottom = $this->cursorY - $height;
        $this->fillRect(self::MARGIN, $bottom, self::CONTENT_WIDTH, $height, $alternate ? [0.965, 0.973, 0.984] : [1, 1, 1]);
        $this->line(self::MARGIN, $bottom, self::MARGIN + self::CONTENT_WIDTH, $bottom, [0.86, 0.89, 0.93], 0.55);
        $x = self::MARGIN;
        foreach ($lines as $index => $cellLines) {
            if ($index > 0) {
                $this->line($x, $bottom, $x, $this->cursorY, [0.90, 0.92, 0.95], 0.4);
            }
            $baseline = $this->cursorY - 12;
            foreach ($cellLines as $lineIndex => $line) {
                $this->text($x + 5, $baseline - ($lineIndex * 9), $line, 6.8, $index === 0 ? 'F2' : 'F1', [0.24, 0.29, 0.36]);
            }
            $x += $widths[$index];
        }
        $this->cursorY = $bottom;
    }

    private function tableRowLayout(array $row, array $widths): array
    {
        $lines = [];
        $maximumLines = count($widths) <= 3 ? 3 : 2;
        foreach ($widths as $index => $width) {
            $maximumCharacters = max(4, (int) floor(($width - 10) / 3.9));
            $lines[] = $this->wrap($this->cell($row[$index] ?? ''), $maximumCharacters, $maximumLines);
        }
        $lineCount = max(array_map('count', $lines));

        return [$lines, max(22.0, 9.0 + ($lineCount * 9.0))];
    }

    private function tableWidths(array $headers, array $rows): array
    {
        $weights = [];
        foreach ($headers as $index => $header) {
            $lengths = [mb_strlen($this->cell($header))];
            foreach (array_slice($rows, 0, 80) as $row) {
                $lengths[] = mb_strlen($this->cell(((array) $row)[$index] ?? ''));
            }
            $weights[] = max(6, min(30, max($lengths)));
        }
        $total = max(1, array_sum($weights));

        return array_map(fn (int $weight) => self::CONTENT_WIDTH * ($weight / $total), $weights);
    }

    private function renderEmptyState(string $message): void
    {
        $this->ensureSpace(58);
        $bottom = $this->cursorY - 48;
        $this->fillRect(self::MARGIN, $bottom, self::CONTENT_WIDTH, 48, [0.965, 0.973, 0.984], [0.86, 0.89, 0.93]);
        $this->text(self::MARGIN + 16, $bottom + 19, $message, 8, 'F1', [0.43, 0.48, 0.56]);
        $this->cursorY = $bottom - 12;
    }

    private function sectionHeading(string $title, string $subtitle = ''): void
    {
        $this->text(self::MARGIN, $this->cursorY, $title, 12, 'F2', [0.14, 0.18, 0.24], 80);
        if ($subtitle !== '') {
            $this->text(self::MARGIN + 300, $this->cursorY + 1, $subtitle, 7, 'F1', [0.43, 0.48, 0.56], 80);
        }
        $this->line(self::MARGIN, $this->cursorY - 8, self::MARGIN + self::CONTENT_WIDTH, $this->cursorY - 8, [0.84, 0.87, 0.91], 0.7);
        $this->cursorY -= 24;
    }

    private function ensureSpace(float $height): void
    {
        if ($this->cursorY - $height < self::CONTENT_BOTTOM) {
            $this->startPage();
        }
    }

    private function appendFooters(): void
    {
        $total = count($this->pages);
        foreach ($this->pages as $index => &$commands) {
            $commands[] = $this->lineCommand(self::MARGIN, 34, self::MARGIN + self::CONTENT_WIDTH, 34, [0.84, 0.87, 0.91], 0.6);
            $footer = 'Fuente: '.$this->sourceLabel.' · '.$this->footerContext;
            if ($this->reportTrace !== '') {
                $footer .= ' · '.$this->reportTrace;
            }
            $commands[] = $this->textCommand(self::MARGIN, 18, $footer, 6.5, 'F1', [0.43, 0.48, 0.56], 125);
            $commands[] = $this->textCommand(754, 18, 'Página '.($index + 1).' de '.$total, 6.5, 'F2', [0.43, 0.48, 0.56]);
        }
        unset($commands);
    }

    private function document(): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            5 => '<< /Type /Font /Subtype /Type3 /Name /LCDSymbols /FontBBox [0 -100 650 650] /FontMatrix [0.001 0 0 0.001 0 0] '
                .'/CharProcs << /epsilonSymbol 6 0 R /notEqual 7 0 R /zeroWidthSpace 8 0 R >> '
                .'/Encoding << /Type /Encoding /Differences [1 /epsilonSymbol /notEqual /zeroWidthSpace] >> '
                .'/FirstChar 1 /LastChar 3 /Widths [600 650 0] /Resources << >> /ToUnicode 9 0 R >>',
            6 => $this->stream("600 0 d0\n60 w 1 J 1 j\n70 430 m 130 560 360 570 500 480 c\n380 505 195 450 130 345 c\n80 265 110 140 220 90 c\n330 35 480 95 535 210 c\nS\n130 345 m 460 345 l S"),
            7 => $this->stream("650 0 d0\n60 w 1 J 1 j\n50 370 m 600 370 l S\n50 180 m 600 180 l S\n500 560 m 150 20 l S"),
            8 => $this->stream('0 0 d0'),
            9 => $this->stream($this->symbolToUnicodeCmap()),
        ];
        $kids = [];
        foreach ($this->pages as $index => $commands) {
            $pageObject = 10 + ($index * 2);
            $contentObject = $pageObject + 1;
            $kids[] = $pageObject.' 0 R';
            $stream = implode("\n", $commands);
            $objects[$pageObject] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /ProcSet [/PDF /Text] /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >> >> /Contents {$contentObject} 0 R >>";
            $objects[$contentObject] = $this->stream($stream);
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($this->pages).' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $maximum = max(array_keys($objects));
        $pdf .= "xref\n0 ".($maximum + 1)."\n0000000000 65535 f \n";
        for ($id = 1; $id <= $maximum; $id++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$id] ?? 0)."\n";
        }
        $pdf .= "trailer\n<< /Size ".($maximum + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function wrap(string $value, int $maximumCharacters, int $maximumLines): array
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
        if ($value === '') {
            return ['-'];
        }
        $words = preg_split('/\s+/u', $value) ?: [$value];
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            if (mb_strlen($word) > $maximumCharacters) {
                $word = mb_strimwidth($word, 0, $maximumCharacters - 1, '…');
            }
            $candidate = $current === '' ? $word : $current.' '.$word;
            if (mb_strlen($candidate) <= $maximumCharacters) {
                $current = $candidate;

                continue;
            }
            $lines[] = $current;
            $current = $word;
            if (count($lines) === $maximumLines - 1) {
                break;
            }
        }
        if ($current !== '' && count($lines) < $maximumLines) {
            $lines[] = $current;
        }
        if (implode(' ', $lines) !== $value) {
            $last = count($lines) - 1;
            $lines[$last] = mb_strimwidth($lines[$last], 0, max(2, $maximumCharacters - 1), '…');
        }

        return $lines ?: ['-'];
    }

    private function text(float $x, float $y, mixed $value, float $size, string $font = 'F1', array $color = [0, 0, 0], ?int $maximumCharacters = null): void
    {
        $this->add($this->textCommand($x, $y, $value, $size, $font, $color, $maximumCharacters));
    }

    private function textCommand(float $x, float $y, mixed $value, float $size, string $font, array $color, ?int $maximumCharacters = null): string
    {
        $text = $this->cell($value);
        if ($maximumCharacters !== null) {
            $text = mb_strimwidth($text, 0, $maximumCharacters, '…');
        }

        return $this->fillColor($color).' '.$this->strokeColor($color).' BT /'.$font.' '.$this->number($size).' Tf '
            .$this->number($x).' '.$this->number($y).' Td '.$this->textOperators($text, $font, $size).' ET';
    }

    private function fillRect(float $x, float $y, float $width, float $height, array $fill, ?array $stroke = null): void
    {
        $command = $this->fillColor($fill).' '.$this->number($x).' '.$this->number($y).' '.$this->number($width).' '.$this->number($height).' re f';
        if ($stroke !== null) {
            $command .= ' '.$this->strokeColor($stroke).' 0.6 w '.$this->number($x).' '.$this->number($y).' '.$this->number($width).' '.$this->number($height).' re S';
        }
        $this->add($command);
    }

    private function line(float $x1, float $y1, float $x2, float $y2, array $color, float $width = 1, ?array $dash = null): void
    {
        $this->add($this->lineCommand($x1, $y1, $x2, $y2, $color, $width, $dash));
    }

    private function lineCommand(float $x1, float $y1, float $x2, float $y2, array $color, float $width = 1, ?array $dash = null): string
    {
        $dashCommand = $dash ? '['.implode(' ', array_map([$this, 'number'], $dash)).'] 0 d ' : '[] 0 d ';

        return $this->strokeColor($color).' '.$this->number($width).' w '.$dashCommand.$this->number($x1).' '.$this->number($y1).' m '.$this->number($x2).' '.$this->number($y2).' l S';
    }

    private function add(string $command): void
    {
        $this->pages[$this->pageIndex][] = $command;
    }

    private function fillColor(array $color): string
    {
        return implode(' ', array_map([$this, 'number'], $color)).' rg';
    }

    private function strokeColor(array $color): string
    {
        return implode(' ', array_map([$this, 'number'], $color)).' RG';
    }

    private function number(float|int $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
    }

    private function numeric(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        $normalized = str_replace(['.', ',', '%', ' '], ['', '.', '', ''], $this->cell($value));

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function cell(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }
        if (is_array($value)) {
            return implode(', ', array_map([$this, 'cell'], $value));
        }

        return trim((string) ($value ?? ''));
    }

    /**
     * WinAnsi remains compact for the regular report text. The official corpus
     * also contains U+03F5, U+2260 and U+200B; those code points are emitted with
     * a tiny Type3 font and an explicit ToUnicode map so both the visible glyph
     * and copied/extracted text retain the canonical Unicode value.
     */
    private function textOperators(string $value, string $font, float $size): string
    {
        if (class_exists(\Normalizer::class)) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_C) ?: $value;
        }

        $special = [
            "\u{03F5}" => '01',
            "\u{2260}" => '02',
            "\u{200B}" => '03',
        ];
        $operators = [];
        $regular = '';
        $flush = function () use (&$regular, &$operators, $font, $size): void {
            if ($regular === '') {
                return;
            }
            $encoded = iconv('UTF-8', 'Windows-1252//IGNORE', $regular);
            if ($encoded === false) {
                throw new \RuntimeException('No se pudo codificar el texto PDF en WinAnsi.');
            }
            $operators[] = '/'.$font.' '.$this->number($size).' Tf ('.$this->escapePdfLiteral($encoded).') Tj';
            $regular = '';
        };

        foreach (preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            if (isset($special[$character])) {
                $flush();
                $operators[] = '/F3 '.$this->number($size).' Tf <'.$special[$character].'> Tj';

                continue;
            }

            $encoded = iconv('UTF-8', 'Windows-1252//IGNORE', $character);
            $roundTrip = $encoded === false ? false : iconv('Windows-1252', 'UTF-8//IGNORE', $encoded);
            if ($roundTrip !== $character) {
                throw new \RuntimeException(
                    'El PDF contiene un carácter Unicode sin glifo auditable (U+'
                    .strtoupper(str_pad(dechex(mb_ord($character)), 4, '0', STR_PAD_LEFT)).'); usa XLSX.',
                );
            }
            $regular .= $character;
        }
        $flush();

        return implode(' ', $operators) ?: '/'.$font.' '.$this->number($size).' Tf () Tj';
    }

    private function escapePdfLiteral(string $encoded): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $encoded);
    }

    private function stream(string $contents): string
    {
        return '<< /Length '.strlen($contents).">>\nstream\n{$contents}\nendstream";
    }

    private function symbolToUnicodeCmap(): string
    {
        return <<<'CMAP'
/CIDInit /ProcSet findresource begin
12 dict begin
begincmap
/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def
/CMapName /LCDSymbols-UCS def
/CMapType 2 def
1 begincodespacerange
<01> <03>
endcodespacerange
3 beginbfchar
<01> <03F5>
<02> <2260>
<03> <200B>
endbfchar
endcmap
CMapName currentdict /CMap defineresource pop
end
end
CMAP;
    }
}
