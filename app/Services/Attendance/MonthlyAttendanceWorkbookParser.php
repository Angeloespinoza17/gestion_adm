<?php

namespace App\Services\Attendance;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MonthlyAttendanceWorkbookParser
{
    private const MONTHS = [
        'enero' => 1,
        'febrero' => 2,
        'marzo' => 3,
        'abril' => 4,
        'mayo' => 5,
        'junio' => 6,
        'julio' => 7,
        'agosto' => 8,
        'septiembre' => 9,
        'setiembre' => 9,
        'octubre' => 10,
        'noviembre' => 11,
        'diciembre' => 12,
    ];

    /**
     * @return array{year:int,month:int,month_label:string,sheets:array<int,string>,rows:array<int,array<string,mixed>>,summary:array<string,mixed>}
     */
    public function parse(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
        } catch (\Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'file' => 'No fue posible leer el Excel. Verifica que sea un archivo .xls o .xlsx válido.',
            ]);
        }

        try {
            $periods = [];
            $rows = [];
            $sheetNames = [];

            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                $sheetNames[] = $worksheet->getTitle();
                $period = $this->periodFromWorksheet($worksheet);
                if ($period) {
                    $periods[] = $period;
                }
                $rows = [...$rows, ...$this->rowsFromWorksheet($worksheet, $period)];
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }

        if ($periods === []) {
            throw ValidationException::withMessages([
                'file' => 'No se encontró un período mensual en el Excel (por ejemplo, “Marzo 2026”).',
            ]);
        }

        $periodKeys = array_values(array_unique(array_map(
            static fn (array $period): string => $period['year'].'-'.str_pad((string) $period['month'], 2, '0', STR_PAD_LEFT),
            $periods,
        )));
        if (count($periodKeys) !== 1) {
            throw ValidationException::withMessages([
                'file' => 'Las hojas del Excel no corresponden a un único mes y año.',
            ]);
        }
        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => 'El Excel no contiene filas de estudiantes con RUT y asistencia reconocibles.',
            ]);
        }

        $period = $periods[0];
        $present = array_sum(array_column($rows, 'present_days'));
        $absent = array_sum(array_column($rows, 'absent_days'));
        $possible = $present + $absent;

        return [
            'year' => $period['year'],
            'month' => $period['month'],
            'month_label' => $period['month_label'],
            'sheets' => $sheetNames,
            'rows' => $rows,
            'summary' => [
                'students' => count($rows),
                'present' => $present,
                'absent' => $absent,
                'possible' => $possible,
                'attendance_rate' => $possible > 0 ? round(($present / $possible) * 100, 2) : null,
                'pie' => count(array_filter($rows, static fn (array $row): bool => $row['is_pie'])),
                'sep_priority' => count(array_filter($rows, static fn (array $row): bool => $row['is_sep_priority'])),
                'sep_preferential' => count(array_filter($rows, static fn (array $row): bool => $row['is_sep_preferential'])),
            ],
        ];
    }

    /** @return array{year:int,month:int,month_label:string}|null */
    private function periodFromWorksheet(Worksheet $worksheet): ?array
    {
        for ($row = 1; $row <= min(6, $worksheet->getHighestDataRow()); $row++) {
            for ($column = 1; $column <= min(8, $this->highestDataColumnIndex($worksheet)); $column++) {
                $value = trim((string) $worksheet->getCell([$column, $row])->getValue());
                if ($value === '') {
                    continue;
                }
                $normalized = $this->normalize($value);
                foreach (self::MONTHS as $label => $month) {
                    if (str_contains($normalized, $label) && preg_match('/\b(20\d{2})\b/', $normalized, $match)) {
                        return [
                            'year' => (int) $match[1],
                            'month' => $month,
                            'month_label' => ucfirst($label),
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  array{year:int,month:int,month_label:string}|null  $period
     * @return array<int,array<string,mixed>>
     */
    private function rowsFromWorksheet(Worksheet $worksheet, ?array $period): array
    {
        if (! $period) {
            return [];
        }

        $headerRow = $this->findHeaderRow($worksheet);
        if (! $headerRow) {
            return [];
        }

        $columns = $this->mapColumns($worksheet, $headerRow);
        foreach (['course', 'given_names', 'paternal_surname', 'run', 'dv', 'present', 'absent', 'class_days'] as $required) {
            if (! isset($columns[$required])) {
                return [];
            }
        }

        $rows = [];
        for ($rowNumber = $headerRow + 1; $rowNumber <= $worksheet->getHighestDataRow(); $rowNumber++) {
            $run = preg_replace('/\D+/', '', $this->cell($worksheet, $columns['run'], $rowNumber));
            $dv = strtoupper(preg_replace('/[^0-9Kk]/', '', $this->cell($worksheet, $columns['dv'], $rowNumber)));
            if ($run === '' || $dv === '') {
                continue;
            }

            $course = $this->cell($worksheet, $columns['course'], $rowNumber);
            $givenNames = $this->cell($worksheet, $columns['given_names'], $rowNumber);
            $paternalSurname = $this->cell($worksheet, $columns['paternal_surname'], $rowNumber);
            $maternalSurname = isset($columns['maternal_surname'])
                ? $this->cell($worksheet, $columns['maternal_surname'], $rowNumber)
                : '';
            $sourceName = trim(implode(' ', array_filter([$paternalSurname, $maternalSurname, $givenNames])));
            if ($course === '' || $sourceName === '') {
                continue;
            }

            $dailyRecords = [];
            foreach ($columns['days'] ?? [] as $day => $column) {
                if (! checkdate($period['month'], (int) $day, $period['year'])) {
                    continue;
                }
                $symbol = trim($this->cell($worksheet, $column, $rowNumber));
                $status = $this->statusFromSymbol($symbol);
                if (! $status) {
                    continue;
                }
                $dailyRecords[] = [
                    'day' => (int) $day,
                    'date' => CarbonImmutable::create($period['year'], $period['month'], (int) $day)->toDateString(),
                    'status' => $status,
                    'symbol' => $symbol,
                ];
            }

            $presentDays = $this->integer($this->cell($worksheet, $columns['present'], $rowNumber));
            $absentDays = $this->integer($this->cell($worksheet, $columns['absent'], $rowNumber));
            $classDays = $this->integer($this->cell($worksheet, $columns['class_days'], $rowNumber));
            $possible = $presentDays + $absentDays;

            $rows[] = [
                'source_sheet' => $worksheet->getTitle(),
                'source_row' => $rowNumber,
                'source_course_name' => $course,
                'list_number' => isset($columns['list_number']) ? $this->integer($this->cell($worksheet, $columns['list_number'], $rowNumber)) : null,
                'given_names' => $givenNames,
                'paternal_surname' => $paternalSurname,
                'maternal_surname' => $maternalSurname,
                'source_name' => $sourceName,
                'source_rut' => ltrim($run, '0').'-'.$dv,
                'normalized_rut' => ltrim($run, '0').$dv,
                'present_days' => $presentDays,
                'absent_days' => $absentDays,
                'class_days' => $classDays,
                'attendance_rate' => $possible > 0 ? round(($presentDays / $possible) * 100, 2) : null,
                'is_sep_priority' => isset($columns['priority']) && $this->boolean($this->cell($worksheet, $columns['priority'], $rowNumber)),
                'is_sep_preferential' => isset($columns['preferential']) && $this->boolean($this->cell($worksheet, $columns['preferential'], $rowNumber)),
                'is_pie' => isset($columns['pie']) && $this->boolean($this->cell($worksheet, $columns['pie'], $rowNumber)),
                'daily_records' => $dailyRecords,
            ];
        }

        return $rows;
    }

    private function findHeaderRow(Worksheet $worksheet): ?int
    {
        for ($row = 1; $row <= min(20, $worksheet->getHighestDataRow()); $row++) {
            $first = $this->normalize($this->cell($worksheet, 1, $row));
            $second = $this->normalize($this->cell($worksheet, 2, $row));
            if ($first === 'curso' && (str_contains($second, 'lista') || $second === 'n')) {
                return $row;
            }
        }

        return null;
    }

    /** @return array<string,mixed> */
    private function mapColumns(Worksheet $worksheet, int $headerRow): array
    {
        $columns = ['days' => []];
        for ($column = 1; $column <= $this->highestDataColumnIndex($worksheet); $column++) {
            $raw = trim($this->cell($worksheet, $column, $headerRow));
            $normalized = $this->normalize($raw);
            if (is_numeric($raw) && (int) $raw >= 1 && (int) $raw <= 31) {
                $columns['days'][(int) $raw] = $column;

                continue;
            }

            $key = match (true) {
                $normalized === 'curso' => 'course',
                str_contains($normalized, 'lista') => 'list_number',
                $normalized === 'apellido paterno' => 'paternal_surname',
                $normalized === 'apellido materno' => 'maternal_surname',
                $normalized === 'nombres' => 'given_names',
                in_array($normalized, ['run', 'rut'], true) => 'run',
                $normalized === 'dv' => 'dv',
                in_array($normalized, ['asist', 'asistentes', 'asistencia'], true) => 'present',
                str_starts_with($normalized, 'inasist') => 'absent',
                str_contains($normalized, 'dias de clases') => 'class_days',
                str_contains($normalized, 'prioritario') => 'priority',
                str_contains($normalized, 'preferente') => 'preferential',
                $normalized === 'es pie' || $normalized === 'pie' => 'pie',
                default => null,
            };
            if ($key) {
                $columns[$key] = $column;
            }
        }

        return $columns;
    }

    private function statusFromSymbol(string $symbol): ?string
    {
        $normalized = mb_strtoupper(trim($symbol), 'UTF-8');

        return match ($normalized) {
            '●', '•', 'P', '1' => 'present',
            'X', 'A', '0' => 'absent',
            default => null,
        };
    }

    private function cell(Worksheet $worksheet, int $column, int $row): string
    {
        $value = $worksheet->getCell([$column, $row])->getValue();

        return trim(is_scalar($value) ? (string) $value : '');
    }

    private function integer(string $value): int
    {
        return max(0, (int) round((float) str_replace(',', '.', $value)));
    }

    private function boolean(string $value): bool
    {
        return in_array($this->normalize($value), ['si', 's', '1', 'true', 'x'], true);
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', Str::lower(Str::ascii($value))) ?? '');
    }

    private function highestDataColumnIndex(Worksheet $worksheet): int
    {
        return Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());
    }
}
