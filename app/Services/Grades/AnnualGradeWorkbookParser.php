<?php

namespace App\Services\Grades;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AnnualGradeWorkbookParser
{
    /**
     * @return array{year:int,date_from:?string,date_to:?string,sheets:list<string>,courses:list<array<string,mixed>>,summary:array<string,int>}
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
            $sheets = [];
            $courses = [];
            $ranges = [];
            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                $sheets[] = $worksheet->getTitle();
                $course = $this->courseFromWorksheet($worksheet);
                if (! $course) {
                    continue;
                }
                $courses[] = $course;
                if ($course['date_from'] && $course['date_to']) {
                    $ranges[] = $course['date_from'].'|'.$course['date_to'];
                }
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }

        if ($courses === []) {
            throw ValidationException::withMessages([
                'file' => 'No se encontraron hojas de curso con Estudiantes, RUN, asignaturas y columnas de notas.',
            ]);
        }

        $years = collect($courses)->flatMap(fn (array $course): array => array_filter([
            $course['date_from'] ? (int) substr($course['date_from'], 0, 4) : null,
            $course['date_to'] ? (int) substr($course['date_to'], 0, 4) : null,
        ]))->unique()->values();
        if ($years->count() !== 1) {
            throw ValidationException::withMessages([
                'file' => 'Las hojas no contienen un único año académico reconocible en el rango de fechas.',
            ]);
        }

        $rangeKeys = array_values(array_unique($ranges));
        if (count($rangeKeys) > 1) {
            throw ValidationException::withMessages([
                'file' => 'Las hojas de curso contienen rangos de fecha distintos.',
            ]);
        }

        $summary = [
            'courses' => count($courses),
            'students' => 0,
            'columns' => 0,
            'cells' => 0,
            'numeric' => 0,
            'pending' => 0,
            'not_applicable' => 0,
            'invalid' => 0,
        ];
        foreach ($courses as $course) {
            $summary['students'] += count($course['students']);
            $summary['columns'] += count($course['columns']);
            $summary['cells'] += count($course['students']) * count($course['columns']);
            foreach ($course['columns'] as $column) {
                foreach (['numeric', 'pending', 'not_applicable', 'invalid'] as $kind) {
                    $summary[$kind] += $column['counts'][$kind];
                }
            }
        }

        $firstRange = $rangeKeys[0] ?? null;
        [$dateFrom, $dateTo] = $firstRange ? explode('|', $firstRange, 2) : [null, null];

        return [
            'year' => (int) $years->first(),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'sheets' => $sheets,
            'courses' => $courses,
            'summary' => $summary,
        ];
    }

    /**
     * Reabre el libro para emitir cada celda sin conservar las 56 mil celdas en memoria.
     *
     * @param  callable(array{source_sheet:string,source_row:int,source_column:int,kind:string,raw:?string,numeric_value:?float}):void  $callback
     */
    public function eachCell(string $path, callable $callback): void
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
        } catch (\Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['file' => 'No fue posible reabrir el Excel para importar sus calificaciones.']);
        }

        try {
            foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                $course = $this->courseFromWorksheet($worksheet);
                if (! $course) {
                    continue;
                }
                foreach ($course['students'] as $student) {
                    foreach ($course['columns'] as $column) {
                        $callback([
                            'source_sheet' => $course['source_sheet'],
                            'source_row' => $student['source_row'],
                            'source_column' => $column['source_column'],
                            ...$this->classify($worksheet->getCell([$column['source_column'], $student['source_row']])->getValue()),
                        ]);
                    }
                }
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    /** @return array<string,mixed>|null */
    private function courseFromWorksheet(Worksheet $worksheet): ?array
    {
        $headerRow = $this->findHeaderRow($worksheet);
        if (! $headerRow || $headerRow <= 1) {
            return null;
        }

        $courseName = $this->labelValue($worksheet, 'curso', 8) ?: trim($worksheet->getTitle());
        $dateRange = $this->labelValue($worksheet, 'rango de fecha', 8);
        [$dateFrom, $dateTo] = $this->dateRange($dateRange);
        if ($courseName === '') {
            return null;
        }

        $subjectRow = $headerRow - 1;
        $highestColumn = $this->highestDataColumnIndex($worksheet);
        $columns = [];
        $currentSubject = '';
        $subjectOrdinals = [];
        $headerOccurrences = [];
        for ($column = 4; $column <= $highestColumn; $column++) {
            $subjectValue = $this->cell($worksheet, $column, $subjectRow);
            if ($subjectValue !== '') {
                $currentSubject = $subjectValue;
            }
            $header = $this->cell($worksheet, $column, $headerRow);
            if ($currentSubject === '' || $header === '') {
                continue;
            }
            $subjectKey = $this->normalize($currentSubject);
            $headerKey = $this->normalize($header);
            $subjectOrdinals[$subjectKey] = ($subjectOrdinals[$subjectKey] ?? 0) + 1;
            $occurrenceKey = $subjectKey.'|'.$headerKey;
            $headerOccurrences[$occurrenceKey] = ($headerOccurrences[$occurrenceKey] ?? 0) + 1;
            $columns[] = [
                'source_column' => $column,
                'source_subject_name' => $currentSubject,
                'normalized_subject' => $subjectKey,
                'source_header' => $header,
                'normalized_header' => $headerKey,
                'header_occurrence' => $headerOccurrences[$occurrenceKey],
                'subject_ordinal' => $subjectOrdinals[$subjectKey],
                'counts' => ['numeric' => 0, 'pending' => 0, 'not_applicable' => 0, 'invalid' => 0],
            ];
        }
        if ($columns === []) {
            return null;
        }

        $students = [];
        for ($row = $headerRow + 1; $row <= $worksheet->getHighestDataRow(); $row++) {
            $listNumber = $this->integerOrNull($this->cell($worksheet, 1, $row));
            $name = $this->cell($worksheet, 2, $row);
            $sourceRut = $this->cell($worksheet, 3, $row);
            if ($listNumber === null || $name === '' || $sourceRut === '') {
                continue;
            }

            foreach ($columns as $index => $column) {
                $classified = $this->classify($worksheet->getCell([$column['source_column'], $row])->getValue());
                $columns[$index]['counts'][$classified['kind']]++;
            }
            $students[] = [
                'source_row' => $row,
                'list_number' => $listNumber,
                'source_name' => $name,
                'source_rut' => $sourceRut,
                'normalized_rut' => $this->normalizeRut($sourceRut),
            ];
        }

        if ($students === []) {
            return null;
        }

        return [
            'source_sheet' => $worksheet->getTitle(),
            'source_course_name' => $courseName,
            'normalized_course' => $this->normalizeCourse($courseName),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'header_row' => $headerRow,
            'columns' => $columns,
            'students' => $students,
        ];
    }

    private function findHeaderRow(Worksheet $worksheet): ?int
    {
        for ($row = 1; $row <= min(20, $worksheet->getHighestDataRow()); $row++) {
            $first = $this->normalize($this->cell($worksheet, 1, $row));
            $second = $this->normalize($this->cell($worksheet, 2, $row));
            $third = $this->normalize($this->cell($worksheet, 3, $row));
            if (str_contains($first, 'lista') && $second === 'estudiantes' && in_array($third, ['run', 'rut'], true)) {
                return $row;
            }
        }

        return null;
    }

    private function labelValue(Worksheet $worksheet, string $label, int $maxRows): string
    {
        $needle = $this->normalize($label);
        for ($row = 1; $row <= min($maxRows, $worksheet->getHighestDataRow()); $row++) {
            for ($column = 1; $column <= min(8, $this->highestDataColumnIndex($worksheet)); $column++) {
                if (rtrim($this->normalize($this->cell($worksheet, $column, $row)), ':') === $needle) {
                    return $this->cell($worksheet, $column + 1, $row);
                }
            }
        }

        return '';
    }

    /** @return array{0:?string,1:?string} */
    private function dateRange(string $value): array
    {
        preg_match_all('/\b(\d{1,2})[\/.\-](\d{1,2})[\/.\-](20\d{2})\b/u', $value, $matches, PREG_SET_ORDER);
        if (count($matches) < 2) {
            return [null, null];
        }

        try {
            return [
                CarbonImmutable::create((int) $matches[0][3], (int) $matches[0][2], (int) $matches[0][1])->toDateString(),
                CarbonImmutable::create((int) $matches[1][3], (int) $matches[1][2], (int) $matches[1][1])->toDateString(),
            ];
        } catch (\Throwable) {
            return [null, null];
        }
    }

    /** @return array{kind:string,raw:?string,numeric_value:?float} */
    private function classify(mixed $value): array
    {
        $raw = trim(is_scalar($value) ? (string) $value : '');
        $upper = mb_strtoupper($raw, 'UTF-8');
        if ($raw === '' || $upper === 'P' || in_array($upper, ['PENDIENTE', 'PEND'], true)) {
            return ['kind' => 'pending', 'raw' => $raw === '' ? null : $raw, 'numeric_value' => null];
        }
        if (in_array($upper, ['-', '—', '–', 'N/A', 'NA'], true)) {
            return ['kind' => 'not_applicable', 'raw' => $raw, 'numeric_value' => null];
        }
        $number = str_replace(',', '.', $raw);
        if (is_numeric($number)) {
            $numeric = round((float) $number, 2);
            if ($numeric >= 1 && $numeric <= 7) {
                return ['kind' => 'numeric', 'raw' => $raw, 'numeric_value' => $numeric];
            }
        }

        return ['kind' => 'invalid', 'raw' => Str::limit($raw, 80, ''), 'numeric_value' => null];
    }

    public function normalizeCourse(string $value): string
    {
        $value = preg_replace('/[º°ª]/u', '', $value) ?? $value;

        return preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii($value))) ?? '';
    }

    public function normalizeSubject(string $value): string
    {
        $value = preg_replace('/^\s*\(\*{1,2}\)\s*/u', '', $value) ?? $value;

        return $this->normalize($value);
    }

    public function normalizeRut(string $value): string
    {
        return ltrim(strtoupper(preg_replace('/[^0-9Kk]/', '', $value) ?? ''), '0');
    }

    public function normalizeName(string $value): string
    {
        $tokens = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($value)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        sort($tokens, SORT_STRING);

        return implode(' ', $tokens);
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($value))) ?? '');
    }

    private function cell(Worksheet $worksheet, int $column, int $row): string
    {
        $value = $worksheet->getCell([$column, $row])->getValue();

        return trim(is_scalar($value) ? (string) $value : '');
    }

    private function integerOrNull(string $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function highestDataColumnIndex(Worksheet $worksheet): int
    {
        return Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());
    }
}
