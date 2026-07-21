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

    public function build(string $title, array $metadata, array $sections, array $dashboard = []): string
    {
        $this->pages = [];
        $this->pageIndex = -1;
        $this->title = $title;
        $this->footerContext = $this->cell($metadata['periodo'] ?? '');
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

    private function startPage(bool $first = false): void
    {
        $this->pages[] = [];
        $this->pageIndex = count($this->pages) - 1;
        $this->fillRect(0, 0, self::PAGE_WIDTH, self::PAGE_HEIGHT, [1, 1, 1]);

        if ($first) {
            $this->fillRect(0, 522, self::PAGE_WIDTH, 73, [0.12, 0.17, 0.28]);
            $this->fillRect(0, 519, self::PAGE_WIDTH, 3, [0.16, 0.55, 0.40]);
            $this->text(32, 570, 'CNSC GESTIÓN', 8, 'F2', [0.72, 0.82, 0.94]);
            $this->text(32, 541, $this->title, 20, 'F2', [1, 1, 1], 86);
            $this->text(690, 570, 'REPORTE INSTITUCIONAL', 7, 'F2', [0.72, 0.82, 0.94]);
            $this->cursorY = 505;

            return;
        }

        $this->fillRect(0, 554, self::PAGE_WIDTH, 41, [0.12, 0.17, 0.28]);
        $this->fillRect(0, 551, self::PAGE_WIDTH, 3, [0.16, 0.55, 0.40]);
        $this->text(32, 570, 'CNSC GESTIÓN', 8, 'F2', [0.72, 0.82, 0.94]);
        $this->text(142, 568, $this->title, 13, 'F2', [1, 1, 1], 72);
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
        $width = (self::CONTENT_WIDTH - ($gap * 3)) / 4;
        $height = 42.0;
        $bottom = $this->cursorY - $height;

        foreach ($cards as $index => [$label, $value]) {
            $x = self::MARGIN + (($width + $gap) * $index);
            $this->fillRect($x, $bottom, $width, $height, [0.96, 0.97, 0.985], [0.86, 0.89, 0.93]);
            $this->fillRect($x, $bottom, 3, $height, [0.25, 0.32, 0.54]);
            $this->text($x + 11, $bottom + 27, $label, 6.5, 'F2', [0.43, 0.48, 0.56], 26);
            $this->text($x + 11, $bottom + 11, $this->cell($value), 8.5, 'F2', [0.16, 0.20, 0.27], 30);
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

        $cards = [
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
        $present = $this->numeric($values['Presentes'] ?? 0);
        $absent = $this->numeric($values['Ausentes'] ?? 0);
        $justified = min($absent, $this->numeric($values['Justificadas'] ?? 0));
        $unjustified = min(max(0, $absent - $justified), $this->numeric($values['Injustificadas'] ?? 0));
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
            $commands[] = $this->textCommand(self::MARGIN, 18, 'Fuente: registros de asistencia del sistema · '.$this->footerContext, 6.5, 'F1', [0.43, 0.48, 0.56], 125);
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
        ];
        $kids = [];
        foreach ($this->pages as $index => $commands) {
            $pageObject = 5 + ($index * 2);
            $contentObject = $pageObject + 1;
            $kids[] = $pageObject.' 0 R';
            $stream = implode("\n", $commands);
            $objects[$pageObject] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /ProcSet [/PDF /Text] /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentObject} 0 R >>";
            $objects[$contentObject] = '<< /Length '.strlen($stream).">>\nstream\n{$stream}\nendstream";
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

        return $this->fillColor($color).' BT /'.$font.' '.$this->number($size).' Tf '.$this->number($x).' '.$this->number($y).' Td ('.$this->escape($text).') Tj ET';
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

    private function escape(string $value): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: $value;

        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $encoded);
    }
}
