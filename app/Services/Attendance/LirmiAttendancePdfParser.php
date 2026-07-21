<?php

namespace App\Services\Attendance;

use App\Services\Attendance\Contracts\AttendanceImportParser;
use DateTimeImmutable;
use RuntimeException;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Throwable;

class LirmiAttendancePdfParser implements AttendanceImportParser
{
    private const MONTHS = [
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
        'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
        'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10,
        'noviembre' => 11, 'diciembre' => 12,
    ];

    public function supports(string $path): bool
    {
        try {
            $page = $this->pdfParser()->parseFile($path)->getPages()[0] ?? null;
            $text = $page?->getText() ?? '';

            return (bool) preg_match('/CONTROL\s+SUBVENCIONES/iu', $text)
                && (bool) preg_match('/Asistencia\s+Diaria/iu', $text);
        } catch (Throwable) {
            return false;
        }
    }

    public function parse(string $path): array
    {
        $this->ensureImportMemoryLimit();

        try {
            $pages = $this->pdfParser()->parseFile($path)->getPages();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No fue posible leer el PDF de asistencia. Verifica que no esté dañado o protegido.',
                previous: $exception,
            );
        }

        if (count($pages) !== 1) {
            throw new RuntimeException('El formato mensual Lirmi debe contener exactamente una página por curso.');
        }

        return $this->parsePage($pages[0]->getText(), $pages[0]->getDataTm());
    }

    public function parsePage(string $text, array $dataTm): array
    {
        if (! preg_match('/CONTROL\s+SUBVENCIONES/iu', $text) || ! preg_match('/Asistencia\s+Diaria/iu', $text)) {
            throw new RuntimeException('El archivo no corresponde al formato mensual de asistencia Lirmi.');
        }

        [$month, $year, $monthLabel] = $this->extractPeriod($text);
        $courseName = $this->extractCourseName($text);
        $items = $this->positionedItems($dataTm);
        $daysInMonth = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');
        [$headerY, $dayColumns, $totalColumns] = $this->resolveColumns($items, $daysInMonth);
        $rowYs = $this->resolveStudentRows($items, $headerY, $totalColumns['total']);
        $characterAdvances = $this->resolveCharacterAdvances($items, $headerY);

        if ($rowYs === []) {
            throw new RuntimeException('No se encontraron filas de estudiantes en el PDF de asistencia.');
        }

        $students = [];

        foreach ($rowYs as $index => $rowY) {
            $nextRowY = $rowYs[$index + 1] ?? ($rowY - 18);
            $name = $this->resolveStudentName($items, $rowY, $nextRowY, $characterAdvances);
            $records = $this->resolveStudentRecords($items, $rowY, $dayColumns, $year, $month);
            $present = count(array_filter($records, static fn (array $record) => $record['status'] === 'present'));
            $absent = count($records) - $present;
            $printed = [
                $this->numericValueNear($items, $totalColumns['present'], $rowY),
                $this->numericValueNear($items, $totalColumns['absent'], $rowY),
                $this->numericValueNear($items, $totalColumns['total'], $rowY),
            ];

            if ($name === '') {
                throw new RuntimeException(sprintf('No se pudo leer el nombre de la fila %d.', $index + 1));
            }

            if ($printed !== [$present, $absent, count($records)]) {
                throw new RuntimeException(sprintf(
                    'La fila %d (%s) no cuadra con sus totales impresos: %d/%d/%d versus %d/%d/%d.',
                    $index + 1, $name, $present, $absent, count($records), ...$printed,
                ));
            }

            $students[] = [
                'row' => $index + 1,
                'name' => $name,
                'present' => $present,
                'absent' => $absent,
                'total' => count($records),
                'attendance_rate' => $this->percentage($present, count($records)),
                'records' => $records,
            ];
        }

        $days = $this->buildDays($students, $daysInMonth, $year, $month);
        $presentTotal = array_sum(array_column($students, 'present'));
        $absentTotal = array_sum(array_column($students, 'absent'));
        $possibleTotal = $presentTotal + $absentTotal;

        return [
            'document' => [
                'source' => 'lirmi_pdf',
                'format' => 'control_subvenciones_asistencia_diaria',
                'course_name' => $courseName,
                'month' => $month,
                'month_label' => $monthLabel,
                'year' => $year,
                'period' => sprintf('%04d-%02d', $year, $month),
            ],
            'summary' => [
                'students' => count($students),
                'school_days' => count($days),
                'present' => $presentTotal,
                'absent' => $absentTotal,
                'possible' => $possibleTotal,
                'attendance_rate' => $this->percentage($presentTotal, $possibleTotal),
                'average_daily_attendance' => $days !== [] ? round($presentTotal / count($days), 2) : 0.0,
                'students_below_85' => count(array_filter(
                    $students,
                    static fn (array $student) => $student['total'] > 0 && $student['attendance_rate'] < 85,
                )),
                'anomaly_days' => count(array_filter($days, static fn (array $day) => $day['is_anomaly'])),
            ],
            'days' => $days,
            'students' => $students,
            'validation' => $this->buildValidation($students, $days, $presentTotal, $absentTotal, $possibleTotal),
        ];
    }

    private function buildDays(array $students, int $daysInMonth, int $year, int $month): array
    {
        $days = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $records = [];
            foreach ($students as $student) {
                $record = collect($student['records'])->firstWhere('day', $day);
                if ($record) {
                    $records[] = $record;
                }
            }

            if ($records === []) {
                continue;
            }

            $present = count(array_filter($records, static fn (array $record) => $record['status'] === 'present'));
            $absent = count($records) - $present;
            $rate = $this->percentage($present, count($records));
            $isAnomaly = $rate === 0.0 && count($records) > 0;
            $days[] = [
                'day' => $day,
                'date' => sprintf('%04d-%02d-%02d', $year, $month, $day),
                'present' => $present,
                'absent' => $absent,
                'enrolled' => count($records),
                'attendance_rate' => $rate,
                'is_anomaly' => $isAnomaly,
                'confirmation_status' => $isAnomaly ? 'pending_confirmation' : 'confirmed',
            ];
        }

        return $days;
    }

    private function pdfParser(): Parser
    {
        $config = new Config;
        $config->setRetainImageContent(false);

        return new Parser([], $config);
    }

    private function extractPeriod(string $text): array
    {
        $pattern = '/\b('.implode('|', array_keys(self::MONTHS)).')\s+(20\d{2})\b/iu';
        if (! preg_match($pattern, $text, $matches)) {
            throw new RuntimeException('No se pudo identificar el mes y año del PDF.');
        }

        $monthLabel = mb_strtolower($matches[1]);

        return [self::MONTHS[$monthLabel], (int) $matches[2], ucfirst($monthLabel)];
    }

    private function extractCourseName(string $text): string
    {
        if (! preg_match('/Asistencia\s+Diaria\s*-\s*([^\r\n]+)/iu', $text, $matches)) {
            throw new RuntimeException('No se pudo identificar el curso impreso en el PDF.');
        }

        return trim(str_replace("\u{00A0}", ' ', $matches[1]));
    }

    private function positionedItems(array $dataTm): array
    {
        $items = [];
        foreach ($dataTm as $entry) {
            $matrix = $entry[0] ?? [];
            $text = trim(str_replace("\u{00A0}", ' ', (string) ($entry[1] ?? '')));
            if ($text !== '' && isset($matrix[4], $matrix[5])) {
                $items[] = ['x' => (float) $matrix[4], 'y' => (float) $matrix[5], 'text' => $text];
            }
        }

        return $items;
    }

    private function resolveColumns(array $items, int $daysInMonth): array
    {
        $header = collect($items)->first(static fn (array $item) => preg_match('/d[ií]as/iu', $item['text']));
        $headerY = $header['y'] ?? $this->resolveFragmentedHeaderY($items, $daysInMonth);
        if ($headerY === null) {
            throw new RuntimeException('No se encontró la cabecera de días del PDF.');
        }

        $headerItems = array_values(array_filter($items, static fn (array $item) => abs($item['y'] - $headerY) <= 1.5));
        $dayItems = array_values(array_filter($headerItems, static fn (array $item) => $item['x'] > 230 && $item['x'] < 660 && preg_match('/^\d{1,2}$/', $item['text'])));
        usort($dayItems, static fn (array $left, array $right) => $left['x'] <=> $right['x']);
        $dayColumns = $this->resolveDayColumns($dayItems, $daysInMonth);

        $totals = array_values(array_filter($headerItems, static fn (array $item) => in_array($item['text'], ['A', 'I', 'T'], true) && $item['x'] > 650));
        usort($totals, static fn (array $left, array $right) => $left['x'] <=> $right['x']);
        if (count($totals) < 3) {
            throw new RuntimeException('No se encontraron las columnas de totales del PDF.');
        }

        return [$headerY, $dayColumns, [
            'present' => $totals[0]['x'], 'absent' => $totals[1]['x'], 'total' => $totals[2]['x'],
        ]];
    }

    private function resolveFragmentedHeaderY(array $items, int $daysInMonth): ?float
    {
        $candidateYs = [];
        foreach ($items as $item) {
            if ($item['x'] > 650 && in_array($item['text'], ['A', 'I', 'T'], true)) {
                $candidateYs[(string) round($item['y'], 2)] = $item['y'];
            }
        }

        foreach ($candidateYs as $candidateY) {
            $line = array_values(array_filter(
                $items,
                static fn (array $item) => abs($item['y'] - $candidateY) <= 1.5,
            ));
            $numericFragments = array_filter(
                $line,
                static fn (array $item) => $item['x'] > 230 && $item['x'] < 660 && preg_match('/^\d{1,2}$/', $item['text']),
            );
            $totalLabels = array_unique(array_column(array_filter(
                $line,
                static fn (array $item) => $item['x'] > 650 && in_array($item['text'], ['A', 'I', 'T'], true),
            ), 'text'));

            if (count($numericFragments) >= $daysInMonth && count($totalLabels) === 3) {
                return (float) $candidateY;
            }
        }

        return null;
    }

    private function resolveDayColumns(array $items, int $daysInMonth): array
    {
        $columns = [];
        $cursor = 0;
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $expected = (string) $day;
            $item = $items[$cursor] ?? null;
            if (! $item) {
                if ($columns !== []) {
                    return $columns;
                }

                throw new RuntimeException('La cabecera del PDF no contiene días válidos.');
            }

            if ($item['text'] === $expected) {
                $columns[$day] = $item['x'];
                $cursor++;

                continue;
            }

            $next = $items[$cursor + 1] ?? null;
            if ($next && $expected === $item['text'].$next['text'] && abs($next['x'] - $item['x']) < 6) {
                $columns[$day] = ($item['x'] + $next['x']) / 2;
                $cursor += 2;

                continue;
            }

            throw new RuntimeException(sprintf('No se pudo resolver la columna del día %d.', $day));
        }

        return $columns;
    }

    private function resolveStudentRows(array $items, float $headerY, float $totalX): array
    {
        $rows = [];
        foreach ($items as $item) {
            if (abs($item['x'] - $totalX) <= 4 && $item['y'] < $headerY - 4
                && preg_match('/^\d{1,2}$/', $item['text']) && (int) $item['text'] <= 31) {
                $rows[] = round($item['y'], 2);
            }
        }

        $rows = array_values(array_unique($rows));
        rsort($rows, SORT_NUMERIC);

        return $rows;
    }

    private function resolveCharacterAdvances(array $items, float $headerY): array
    {
        $lines = [];
        foreach ($items as $item) {
            if ($item['x'] < 175 || $item['x'] >= 260 || $item['y'] >= $headerY - 4
                || mb_strlen($item['text']) !== 1 || preg_match('/^\d$/', $item['text'])) {
                continue;
            }

            $lines[(string) round($item['y'], 2)][] = $item;
        }

        $advances = [];
        foreach ($lines as $line) {
            usort($line, static fn (array $left, array $right) => $left['x'] <=> $right['x']);
            for ($index = 0, $last = count($line) - 1; $index < $last; $index++) {
                $current = $line[$index];
                $next = $line[$index + 1];
                $gap = $next['x'] - $current['x'];
                if ($gap < 0.5 || $gap >= 7) {
                    continue;
                }

                $character = $current['text'];
                $advances[$character] = min($advances[$character] ?? INF, $gap);
            }
        }

        return $advances;
    }

    private function resolveStudentName(
        array $items,
        float $rowY,
        float $nextRowY,
        array $characterAdvances,
    ): string {
        $lowerBound = ($rowY + $nextRowY) / 2;
        $nameItems = array_values(array_filter($items, static function (array $item) use ($rowY, $lowerBound) {
            if ($item['x'] < 175 || $item['x'] >= 260 || $item['y'] > $rowY + 2 || $item['y'] < $lowerBound) {
                return false;
            }

            return ! ($item['x'] < 188 && abs($item['y'] - $rowY) <= 2 && preg_match('/^\d{1,2}$/', $item['text']));
        }));

        usort($nameItems, static function (array $left, array $right) {
            return abs($left['y'] - $right['y']) > 1 ? $right['y'] <=> $left['y'] : $left['x'] <=> $right['x'];
        });

        $lines = [];
        foreach ($nameItems as $item) {
            $lines[(string) round($item['y'], 2)][] = $item;
        }

        return trim((string) preg_replace('/\s+/u', ' ', implode(' ', array_map(
            fn (array $line) => $this->joinNameLine($line, $characterAdvances),
            $lines,
        ))));
    }

    private function joinNameLine(array $items, array $characterAdvances): string
    {
        usort($items, static fn (array $left, array $right) => $left['x'] <=> $right['x']);
        $singleCharacters = count(array_filter($items, static fn (array $item) => mb_strlen($item['text']) === 1));
        if ($singleCharacters < max(3, (int) floor(count($items) * 0.8))) {
            return implode(' ', array_column($items, 'text'));
        }

        $text = '';
        $previous = null;
        foreach ($items as $item) {
            if ($previous !== null) {
                $gap = $item['x'] - $previous['x'];
                $expectedAdvance = $characterAdvances[$previous['text']] ?? 2.75;
                $caseBoundary = preg_match('/\p{Ll}/u', $previous['text']) && preg_match('/\p{Lu}/u', $item['text']);
                if ($caseBoundary || $gap > $expectedAdvance + 0.75) {
                    $text .= ' ';
                }
            }

            $text .= $item['text'];
            $previous = $item;
        }

        return $text;
    }

    private function resolveStudentRecords(array $items, float $rowY, array $dayColumns, int $year, int $month): array
    {
        $records = [];
        foreach ($items as $item) {
            $status = $this->statusFromSymbol($item['text']);
            if ($status === null || abs($item['y'] - $rowY) > 2.2) {
                continue;
            }

            $distances = array_map(static fn (float $x) => abs($item['x'] - $x), $dayColumns);
            $minimum = min($distances);
            $day = (int) array_search($minimum, $distances, true);
            $previousX = $dayColumns[max(1, $day - 1)] ?? $dayColumns[$day];
            $nextX = $dayColumns[min(count($dayColumns), $day + 1)] ?? $dayColumns[$day];
            $tolerance = max(3.5, min(abs($dayColumns[$day] - $previousX), abs($nextX - $dayColumns[$day])) * 0.48);
            if ($day < 1 || $minimum > $tolerance) {
                continue;
            }

            $records[$day] = [
                'day' => $day,
                'date' => sprintf('%04d-%02d-%02d', $year, $month, $day),
                'status' => $status,
                'symbol' => $item['text'],
            ];
        }

        ksort($records);

        return array_values($records);
    }

    private function numericValueNear(array $items, float $x, float $y): int
    {
        $fragments = array_values(array_filter($items, static fn (array $item) => abs($item['x'] - $x) <= 4 && abs($item['y'] - $y) <= 2 && preg_match('/^\d+$/', $item['text'])
        ));
        if ($fragments === []) {
            return -1;
        }

        usort($fragments, static fn (array $left, array $right) => $left['x'] <=> $right['x']);

        return (int) implode('', array_column($fragments, 'text'));
    }

    private function statusFromSymbol(string $symbol): ?string
    {
        if (in_array(trim($symbol), ['●', '•'], true)) {
            return 'present';
        }

        return mb_strtoupper(trim($symbol)) === 'X' ? 'absent' : null;
    }

    private function buildValidation(array $students, array $days, int $present, int $absent, int $possible): array
    {
        $checks = [
            ['code' => 'present_totals', 'label' => 'Total de presentes', 'expected' => $present, 'actual' => array_sum(array_column($days, 'present'))],
            ['code' => 'absent_totals', 'label' => 'Total de ausentes', 'expected' => $absent, 'actual' => array_sum(array_column($days, 'absent'))],
            ['code' => 'possible_totals', 'label' => 'Asistencias posibles', 'expected' => $possible, 'actual' => array_sum(array_column($students, 'total'))],
        ];

        return array_map(static fn (array $check) => [
            ...$check,
            'passed' => $check['expected'] === $check['actual'],
            'level' => $check['expected'] === $check['actual'] ? 'success' : 'error',
        ], $checks);
    }

    private function percentage(int|float $part, int|float $total): float
    {
        return $total > 0 ? round(($part / $total) * 100, 2) : 0.0;
    }

    private function ensureImportMemoryLimit(): void
    {
        $current = ini_get('memory_limit');
        if ($current === false || $current === '-1') {
            return;
        }

        $value = (int) $current;
        $bytes = match (strtolower(substr($current, -1))) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };

        if ($bytes < 512 * 1024 * 1024) {
            ini_set('memory_limit', '512M');
        }
    }
}
