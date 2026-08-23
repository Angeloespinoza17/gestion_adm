<?php

namespace App\Services\Accounting;

use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

class BudgetExecutionWorkbookParser
{
    private const MONTH_COLUMNS = [
        'E' => 'january',
        'G' => 'february',
        'I' => 'march',
        'K' => 'april',
        'M' => 'may',
        'O' => 'june',
        'Q' => 'july',
        'S' => 'august',
        'U' => 'september',
        'W' => 'october',
        'Y' => 'november',
        'AA' => 'december',
    ];

    private const SUBSIDIES = [
        'GENERAL' => ['code' => 'general', 'name' => 'Subvención General'],
        'MANTENCION' => ['code' => 'mantencion', 'name' => 'Subvención de Mantención'],
        'SEP' => ['code' => 'sep', 'name' => 'Subvención SEP'],
        'PIE' => ['code' => 'pie', 'name' => 'Subvención PIE'],
    ];

    /**
     * @return array{detected_year:?int,school_name:?string,lines:array<int,array<string,mixed>>,sheets:array<int,string>,reported_through_month:?int}
     */
    public function parse(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'El archivo no es un libro Excel válido.']);
        }

        try {
            $this->assertSafeArchive($zip);
            $sharedStrings = $this->readSharedStrings($zip);
            $sheetTargets = $this->readSheetTargets($zip);
            $parsedSheets = [];

            foreach ($sheetTargets as $sheetName => $target) {
                $subsidy = self::SUBSIDIES[$this->normalize($sheetName)] ?? null;
                if (! $subsidy) {
                    continue;
                }

                $xml = $zip->getFromName($target);
                if ($xml === false) {
                    continue;
                }

                $parsedSheets[$sheetName] = [
                    'subsidy' => $subsidy,
                    'rows' => $this->readRows($xml, $sharedStrings),
                ];
            }
        } finally {
            $zip->close();
        }

        if ($parsedSheets === []) {
            throw ValidationException::withMessages([
                'file' => 'No se encontraron las hojas GENERAL, MANTENCION, SEP o PIE esperadas.',
            ]);
        }

        $years = [];
        $schoolName = null;
        $lines = [];
        $sortOrder = 0;

        foreach ($parsedSheets as $sheetName => $sheet) {
            foreach ($sheet['rows'] as $rowNumber => $cells) {
                if ($rowNumber <= 12) {
                    foreach ($cells as $cell) {
                        if (preg_match('/\b(20\d{2})\b/', (string) ($cell['value'] ?? ''), $match)) {
                            $years[] = (int) $match[1];
                        }
                    }
                }
            }

            if ($this->normalize($sheetName) === 'GENERAL') {
                $schoolName = $this->cleanLabel($sheet['rows'][2]['C']['value'] ?? null);
            }

            $flowType = null;
            $category = null;
            foreach ($sheet['rows'] as $rowNumber => $cells) {
                $label = $this->cleanLabel($cells['B']['value'] ?? null);
                if (! $label) {
                    continue;
                }

                $normalizedLabel = $this->normalize($label);
                if ($normalizedLabel === 'INGRESOS') {
                    $flowType = 'income';
                    $category = null;

                    continue;
                }
                if ($normalizedLabel === 'EGRESOS') {
                    $flowType = 'expense';
                    $category = null;

                    continue;
                }
                if (str_contains($normalizedLabel, 'TOTAL SALIDAS NETAS')) {
                    $flowType = null;
                    $category = null;

                    continue;
                }
                if (! $flowType || str_contains($normalizedLabel, 'TOTAL')) {
                    continue;
                }

                $currencyMarker = (string) ($cells['C']['value'] ?? '');
                $hasExecutionFormula = isset($cells['AC']['formula']);
                $hasAmount = $this->number($cells['D']['value'] ?? null) !== null;
                foreach (array_keys(self::MONTH_COLUMNS) as $column) {
                    $hasAmount = $hasAmount || $this->number($cells[$column]['value'] ?? null) !== null;
                }
                $isLine = str_contains($currencyMarker, '$') || $hasExecutionFormula || $hasAmount;

                if (! $isLine) {
                    $category = $label;

                    continue;
                }

                $line = [
                    'subsidy_code' => $sheet['subsidy']['code'],
                    'subsidy_name' => $sheet['subsidy']['name'],
                    'flow_type' => $flowType,
                    'category' => $category ?: ($flowType === 'income' ? 'Ingresos' : 'Egresos'),
                    'account_name' => $label,
                    'annual_budget' => $this->number($cells['D']['value'] ?? null),
                    'source_sheet' => $sheetName,
                    'source_row' => $rowNumber,
                    'sort_order' => ++$sortOrder,
                ];
                foreach (self::MONTH_COLUMNS as $column => $month) {
                    $line[$month] = $this->number($cells[$column]['value'] ?? null);
                }
                $lines[] = $line;
            }
        }

        if ($lines === []) {
            throw ValidationException::withMessages([
                'file' => 'El libro no contiene cuentas presupuestarias reconocibles para importar.',
            ]);
        }

        $reportedThrough = null;
        foreach (array_values(self::MONTH_COLUMNS) as $index => $month) {
            if (collect($lines)->contains(fn (array $line): bool => abs((float) ($line[$month] ?? 0)) > 0.00001)) {
                $reportedThrough = $index + 1;
            }
        }

        $yearCounts = array_count_values($years);
        arsort($yearCounts);

        return [
            'detected_year' => $yearCounts !== [] ? (int) array_key_first($yearCounts) : null,
            'school_name' => $schoolName,
            'lines' => $lines,
            'sheets' => array_keys($parsedSheets),
            'reported_through_month' => $reportedThrough,
        ];
    }

    private function assertSafeArchive(ZipArchive $zip): void
    {
        $uncompressedBytes = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $uncompressedBytes += (int) ($stat['size'] ?? 0);
            if ($uncompressedBytes > 80 * 1024 * 1024) {
                throw ValidationException::withMessages(['file' => 'El contenido del Excel excede el tamaño seguro permitido.']);
            }
        }
    }

    /** @return array<int, string> */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $content = $zip->getFromName('xl/sharedStrings.xml');
        if ($content === false) {
            return [];
        }

        $xml = $this->xml($content);
        $strings = [];
        foreach ($xml->si as $item) {
            $parts = [];
            if (isset($item->t)) {
                $parts[] = (string) $item->t;
            }
            foreach ($item->r as $run) {
                $parts[] = (string) $run->t;
            }
            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    /** @return array<string, string> */
    private function readSheetTargets(ZipArchive $zip): array
    {
        $workbookContent = $zip->getFromName('xl/workbook.xml');
        $relationshipsContent = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookContent === false || $relationshipsContent === false) {
            throw ValidationException::withMessages(['file' => 'La estructura interna del libro Excel está incompleta.']);
        }

        $relationships = [];
        $relsXml = $this->xml($relationshipsContent);
        foreach ($relsXml->Relationship as $relationship) {
            $attributes = $relationship->attributes();
            $relationships[(string) $attributes['Id']] = (string) $attributes['Target'];
        }

        $targets = [];
        $workbookXml = $this->xml($workbookContent);
        $relationshipNamespace = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        foreach ($workbookXml->sheets->sheet as $sheet) {
            $attributes = $sheet->attributes();
            $relationAttributes = $sheet->attributes($relationshipNamespace);
            $relationId = (string) $relationAttributes['id'];
            $target = $relationships[$relationId] ?? null;
            if (! $target) {
                continue;
            }
            $target = ltrim(str_replace('\\', '/', $target), '/');
            $targets[(string) $attributes['name']] = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
        }

        return $targets;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @return array<int, array<string, array{value:mixed,formula?:string}>>
     */
    private function readRows(string $content, array $sharedStrings): array
    {
        $xml = $this->xml($content);
        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $rowNumber = (int) $row['r'];
            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                if (! preg_match('/^([A-Z]+)\d+$/', $reference, $match)) {
                    continue;
                }
                $type = (string) $cell['t'];
                $raw = isset($cell->v) ? (string) $cell->v : null;
                $value = match ($type) {
                    's' => $sharedStrings[(int) $raw] ?? null,
                    'inlineStr' => $this->inlineString($cell),
                    'b' => $raw === '1',
                    'str' => $raw,
                    default => is_numeric($raw) ? (float) $raw : $raw,
                };
                $rows[$rowNumber][$match[1]] = array_filter([
                    'value' => $value,
                    'formula' => isset($cell->f) ? (string) $cell->f : null,
                ], static fn ($item): bool => $item !== null);
            }
        }

        ksort($rows);

        return $rows;
    }

    private function inlineString(SimpleXMLElement $cell): string
    {
        $parts = [];
        if (isset($cell->is->t)) {
            $parts[] = (string) $cell->is->t;
        }
        foreach ($cell->is->r as $run) {
            $parts[] = (string) $run->t;
        }

        return implode('', $parts);
    }

    private function xml(string $content): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
            if ($xml === false) {
                throw ValidationException::withMessages(['file' => 'No fue posible leer la estructura XML del Excel.']);
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function number(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = preg_replace('/[^0-9,.-]/', '', (string) $value);
        if ($normalized === '' || $normalized === '-') {
            return null;
        }
        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function cleanLabel(mixed $value): ?string
    {
        $label = trim((string) $value);
        $label = preg_replace('/\s+/u', ' ', $label);

        return $label !== '' ? $label : null;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtoupper(trim($value), 'UTF-8');

        return strtr($value, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
    }
}
