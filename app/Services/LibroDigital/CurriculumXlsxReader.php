<?php

namespace App\Services\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;
use XMLReader;
use ZipArchive;

class CurriculumXlsxReader
{
    private const MAX_ARCHIVE_ENTRIES = 2_000;

    private const MAX_UNCOMPRESSED_BYTES = 32_000_000;

    private const MAX_XML_BYTES = 16_000_000;

    private const MAX_SHARED_STRINGS = 100_000;

    private const MAX_ROWS_PER_SHEET = 15_000;

    private const MAX_CELLS_PER_SHEET = 180_000;

    private const MAX_CELL_CHARACTERS = 20_000;

    /** @var array<string, list<string>> */
    public const REQUIRED_HEADERS = [
        'catalogs' => [
            'catalog_code', 'catalog_name', 'version', 'authority', 'source_url',
            'source_sha256', 'effective_from', 'effective_to',
        ],
        'objectives' => [
            'catalog_code', 'catalog_version', 'code', 'objective_type', 'subject_code',
            'level_code', 'grade_code', 'axis_code', 'unit_code', 'description',
            'indicators_json', 'active', 'source_page',
        ],
        'sources' => [
            'source_key', 'source_scope', 'source_name', 'authority', 'document_number',
            'source_url', 'source_sha256', 'effective_from', 'effective_to',
            'curriculum_track', 'subject_code', 'objective_type',
        ],
        'objective_sources' => [
            'objective_code', 'objective_type', 'subject_code', 'level_code', 'grade_code',
            'curriculum_track', 'axis_code', 'source_key', 'source_role', 'source_locator',
        ],
        'links' => [
            'school_rbd', 'academic_year', 'subject_code', 'catalog_code', 'catalog_version',
            'level_code', 'grade_code', 'valid_from', 'valid_to', 'active',
        ],
    ];

    /** @var list<string> */
    public const SUBJECT_HEADERS = [
        'codigo_tecnico', 'asignatura_ambito', 'trayectoria', 'niveles',
        'grados', 'filas_maestras', 'activables', 'disposiciones',
        'pagina_oficial', 'estado',
    ];

    /** @var array<string, list<string>> */
    private const OPTIONAL_HEADERS = [
        'objectives' => ['curriculum_track'],
        'links' => ['curriculum_track'],
    ];

    /** @var array<string, string> */
    private const SHEET_ALIASES = [
        'catalogo' => 'catalogs',
        'catalogos' => 'catalogs',
        'objetivos' => 'objectives',
        'fuentes' => 'sources',
        'objetivofuentes' => 'objective_sources',
        'objetivo_fuentes' => 'objective_sources',
        'vinculos' => 'links',
        'referencias' => 'references',
    ];

    /**
     * @return array{
     *   file_hash:string,
     *   size_bytes:int,
     *   catalogs:list<array<string, mixed>>,
     *   objectives:list<array<string, mixed>>,
     *   sources:list<array<string, mixed>>,
     *   objective_sources:list<array<string, mixed>>,
     *   links:list<array<string, mixed>>,
     *   references:list<array<string, mixed>>
     * }
     */
    public function read(string $path): array
    {
        $realPath = realpath($path);
        if ($realPath === false || ! is_file($realPath) || ! is_readable($realPath)) {
            throw $this->invalid('No se pudo leer el archivo XLSX.', 'LCD_CURRICULUM_XLSX_UNREADABLE');
        }

        $size = filesize($realPath);
        if (! is_int($size) || $size < 1) {
            throw $this->invalid('El archivo XLSX está vacío.', 'LCD_CURRICULUM_XLSX_EMPTY');
        }

        $zip = new ZipArchive;
        if ($zip->open($realPath, ZipArchive::RDONLY) !== true) {
            throw $this->invalid('El archivo no es un contenedor XLSX válido.', 'LCD_CURRICULUM_XLSX_INVALID_CONTAINER');
        }

        try {
            $this->validateArchive($zip);
            $sharedStrings = $this->sharedStrings($zip);
            $dateStyles = $this->dateStyles($zip);
            $sheetPaths = $this->sheetPaths($zip);
            $result = [
                'file_hash' => hash_file('sha256', $realPath),
                'size_bytes' => $size,
                'catalogs' => [],
                'objectives' => [],
                'sources' => [],
                'objective_sources' => [],
                'links' => [],
                'references' => [],
            ];
            $seenSheets = [];

            foreach ($sheetPaths as $sheet) {
                $logicalName = self::SHEET_ALIASES[$this->key($sheet['name'])] ?? null;
                if ($logicalName === null) {
                    continue;
                }
                $seenSheets[$logicalName] = true;

                $xml = $zip->getFromName($sheet['path']);
                if (! is_string($xml)) {
                    throw $this->invalid(
                        "No se pudo leer la hoja {$sheet['name']}.",
                        'LCD_CURRICULUM_XLSX_SHEET_UNREADABLE',
                        [['sheet' => $sheet['name']]],
                    );
                }
                $result[$logicalName] = $this->parseSheet(
                    $xml,
                    $sheet['name'],
                    self::REQUIRED_HEADERS[$logicalName] ?? null,
                    $sharedStrings,
                    $dateStyles,
                );
            }

            foreach (array_keys(self::REQUIRED_HEADERS) as $requiredSheet) {
                if (! isset($seenSheets[$requiredSheet])) {
                    throw $this->invalid(
                        'El XLSX debe contener las hojas Catalogo, Objetivos, Fuentes, ObjetivoFuentes y Vinculos.',
                        'LCD_CURRICULUM_XLSX_REQUIRED_SHEET_MISSING',
                        [['sheet' => $requiredSheet]],
                    );
                }
            }

            return $result;
        } finally {
            $zip->close();
        }
    }

    /**
     * Read the institutional subject inventory included in the governed
     * workbook. This sheet is intentionally separate from the normative
     * import payload: it bootstraps the shared ERP subject catalog without
     * silently changing the five-sheet import contract.
     *
     * @return list<array<string, mixed>>
     */
    public function readSubjects(string $path): array
    {
        $realPath = realpath($path);
        if ($realPath === false || ! is_file($realPath) || ! is_readable($realPath)) {
            throw $this->invalid('No se pudo leer el archivo XLSX.', 'LCD_CURRICULUM_XLSX_UNREADABLE');
        }

        $size = filesize($realPath);
        if (! is_int($size) || $size < 1) {
            throw $this->invalid('El archivo XLSX está vacío.', 'LCD_CURRICULUM_XLSX_EMPTY');
        }

        $zip = new ZipArchive;
        if ($zip->open($realPath, ZipArchive::RDONLY) !== true) {
            throw $this->invalid('El archivo no es un contenedor XLSX válido.', 'LCD_CURRICULUM_XLSX_INVALID_CONTAINER');
        }

        try {
            $this->validateArchive($zip);
            $sharedStrings = $this->sharedStrings($zip);
            $dateStyles = $this->dateStyles($zip);

            foreach ($this->sheetPaths($zip) as $sheet) {
                if ($this->key($sheet['name']) !== 'asignaturas') {
                    continue;
                }

                $xml = $zip->getFromName($sheet['path']);
                if (! is_string($xml)) {
                    throw $this->invalid(
                        'No se pudo leer la hoja Asignaturas.',
                        'LCD_CURRICULUM_XLSX_SHEET_UNREADABLE',
                        [['sheet' => 'Asignaturas']],
                    );
                }

                return $this->parseSheet(
                    $xml,
                    $sheet['name'],
                    self::SUBJECT_HEADERS,
                    $sharedStrings,
                    $dateStyles,
                );
            }

            throw $this->invalid(
                'El XLSX debe contener la hoja Asignaturas.',
                'LCD_CURRICULUM_XLSX_SUBJECTS_SHEET_MISSING',
                [['sheet' => 'Asignaturas']],
            );
        } finally {
            $zip->close();
        }
    }

    private function validateArchive(ZipArchive $zip): void
    {
        if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_ARCHIVE_ENTRIES) {
            throw $this->invalid('El XLSX contiene una cantidad de entradas no permitida.', 'LCD_CURRICULUM_XLSX_ARCHIVE_LIMIT');
        }

        $total = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            if (! is_array($stat)) {
                throw $this->invalid('No se pudo inspeccionar el contenedor XLSX.', 'LCD_CURRICULUM_XLSX_ARCHIVE_INVALID');
            }
            $name = (string) ($stat['name'] ?? '');
            $normalized = str_replace('\\', '/', $name);
            if ($name === '' || str_starts_with($normalized, '/') || preg_match('~(^|/)\.\.(/|$)~', $normalized)) {
                throw $this->invalid('El XLSX contiene una ruta interna no permitida.', 'LCD_CURRICULUM_XLSX_PATH_INVALID');
            }
            if (preg_match('~(^|/)(externalLinks|embeddings|ctrlProps|activeX)(/|$)|vbaProject|\.bin$~i', $normalized)) {
                throw $this->invalid('El XLSX contiene macros, objetos o vínculos externos no permitidos.', 'LCD_CURRICULUM_XLSX_ACTIVE_CONTENT');
            }

            $uncompressed = (int) ($stat['size'] ?? 0);
            $compressed = (int) ($stat['comp_size'] ?? 0);
            $total += $uncompressed;
            if ($uncompressed > self::MAX_XML_BYTES && str_ends_with(strtolower($normalized), '.xml')) {
                throw $this->invalid('Una parte XML del XLSX supera el límite permitido.', 'LCD_CURRICULUM_XLSX_XML_LIMIT');
            }
            if ($compressed > 0 && $uncompressed > 1_000_000 && ($uncompressed / $compressed) > 150) {
                throw $this->invalid('El XLSX presenta una razón de compresión insegura.', 'LCD_CURRICULUM_XLSX_COMPRESSION_LIMIT');
            }
        }

        if ($total > self::MAX_UNCOMPRESSED_BYTES) {
            throw $this->invalid('El contenido descomprimido del XLSX supera el límite permitido.', 'LCD_CURRICULUM_XLSX_ARCHIVE_LIMIT');
        }
        foreach (['[Content_Types].xml', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels'] as $required) {
            if ($zip->locateName($required) === false) {
                throw $this->invalid('El contenedor no tiene la estructura mínima de un XLSX.', 'LCD_CURRICULUM_XLSX_STRUCTURE_INVALID');
            }
        }
    }

    /** @return list<string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $reader = $this->xmlReader($xml, 'sharedStrings');
        $values = [];
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'si') {
                    continue;
                }
                if (count($values) >= self::MAX_SHARED_STRINGS) {
                    throw $this->invalid('El XLSX supera el límite de textos compartidos.', 'LCD_CURRICULUM_XLSX_SHARED_STRING_LIMIT');
                }
                $values[] = $this->textFromFragment($reader->readOuterXml());
            }
        } finally {
            $reader->close();
        }

        return $values;
    }

    /** @return array<int, bool> */
    private function dateStyles(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/styles.xml');
        if (! is_string($xml)) {
            return [];
        }
        $document = $this->dom($xml, 'styles');
        $xpath = new DOMXPath($document);
        $dateFormats = array_fill_keys([14, 15, 16, 17, 22, 27, 30, 36, 50, 57], true);
        foreach ($xpath->query('//*[local-name()="numFmt"]') ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $format = strtolower($node->getAttribute('formatCode'));
            $format = preg_replace('/"[^"]*"|\\\\./', '', $format) ?? $format;
            if (preg_match('/(^|[^a-z])[ymd]+([^a-z]|$)/', $format)) {
                $dateFormats[(int) $node->getAttribute('numFmtId')] = true;
            }
        }

        $styles = [];
        $index = 0;
        foreach ($xpath->query('//*[local-name()="cellXfs"]/*[local-name()="xf"]') ?: [] as $node) {
            $styles[$index++] = $node instanceof DOMElement
                && isset($dateFormats[(int) $node->getAttribute('numFmtId')]);
        }

        return $styles;
    }

    /** @return list<array{name:string,path:string}> */
    private function sheetPaths(ZipArchive $zip): array
    {
        $workbook = $this->dom((string) $zip->getFromName('xl/workbook.xml'), 'workbook');
        $relationships = $this->dom((string) $zip->getFromName('xl/_rels/workbook.xml.rels'), 'workbook relationships');
        $relationXpath = new DOMXPath($relationships);
        $relationMap = [];
        foreach ($relationXpath->query('//*[local-name()="Relationship"]') ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $type = $node->getAttribute('Type');
            $targetMode = strtolower($node->getAttribute('TargetMode'));
            if ($targetMode === 'external') {
                throw $this->invalid('El XLSX contiene relaciones externas no permitidas.', 'LCD_CURRICULUM_XLSX_EXTERNAL_RELATIONSHIP');
            }
            if (! str_ends_with($type, '/worksheet')) {
                continue;
            }
            $target = str_replace('\\', '/', $node->getAttribute('Target'));
            $path = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.ltrim($target, '/');
            if (str_contains($path, '../')) {
                throw $this->invalid('El XLSX contiene una relación interna no permitida.', 'LCD_CURRICULUM_XLSX_PATH_INVALID');
            }
            $relationMap[$node->getAttribute('Id')] = $path;
        }

        $xpath = new DOMXPath($workbook);
        $paths = [];
        foreach ($xpath->query('//*[local-name()="sheets"]/*[local-name()="sheet"]') ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $relationshipId = $node->getAttributeNS(
                'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                'id',
            );
            if ($relationshipId !== '' && isset($relationMap[$relationshipId])) {
                $paths[] = ['name' => $node->getAttribute('name'), 'path' => $relationMap[$relationshipId]];
            }
        }

        return $paths;
    }

    /**
     * @param  list<string>|null  $requiredHeaders
     * @param  list<string>  $sharedStrings
     * @param  array<int, bool>  $dateStyles
     * @return list<array<string, mixed>>
     */
    private function parseSheet(
        string $xml,
        string $sheetName,
        ?array $requiredHeaders,
        array $sharedStrings,
        array $dateStyles,
    ): array {
        $reader = $this->xmlReader($xml, $sheetName);
        $headerRow = null;
        $headerMap = [];
        $rows = [];
        $rowCount = 0;
        $cellCount = 0;
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                    continue;
                }
                $rowNumber = (int) ($reader->getAttribute('r') ?: ($rowCount + 1));
                $cells = $this->rowCells($reader->readOuterXml(), $sharedStrings, $dateStyles, $sheetName, $rowNumber);
                $cellCount += count($cells);
                if ($cellCount > self::MAX_CELLS_PER_SHEET) {
                    throw $this->invalid("La hoja {$sheetName} supera el límite de celdas.", 'LCD_CURRICULUM_XLSX_CELL_LIMIT');
                }

                if ($requiredHeaders === null) {
                    if ($this->hasContent($cells)) {
                        $rows[] = ['_sheet' => $sheetName, '_row' => $rowNumber, 'values' => array_values($cells)];
                    }

                    continue;
                }

                if ($headerRow === null && $rowNumber <= 30) {
                    $candidate = [];
                    foreach ($cells as $column => $value) {
                        $key = $this->key((string) $value);
                        if ($key !== '') {
                            $candidate[$column] = $key;
                        }
                    }
                    if (collect($requiredHeaders)->every(fn (string $header): bool => in_array($header, $candidate, true))) {
                        $allowedHeaders = [...$requiredHeaders, ...(self::OPTIONAL_HEADERS[self::SHEET_ALIASES[$this->key($sheetName)] ?? ''] ?? [])];
                        $unexpected = array_values(array_diff(array_values($candidate), $allowedHeaders));
                        if ($unexpected !== []) {
                            throw $this->invalid(
                                "La hoja {$sheetName} contiene encabezados no soportados.",
                                'LCD_CURRICULUM_XLSX_UNEXPECTED_HEADER',
                                [['sheet' => $sheetName, 'headers' => $unexpected]],
                            );
                        }
                        $headerRow = $rowNumber;
                        $headerMap = $candidate;
                    }

                    continue;
                }

                if ($headerRow === null || $rowNumber <= $headerRow) {
                    continue;
                }
                if (! $this->hasContent($cells)) {
                    continue;
                }
                $row = ['_sheet' => $sheetName, '_row' => $rowNumber];
                foreach ($headerMap as $column => $field) {
                    $row[$field] = $cells[$column] ?? null;
                }
                $rows[] = $row;
                $rowCount++;
                if ($rowCount > self::MAX_ROWS_PER_SHEET) {
                    throw $this->invalid("La hoja {$sheetName} supera el límite de filas.", 'LCD_CURRICULUM_XLSX_ROW_LIMIT');
                }
            }
        } finally {
            $reader->close();
        }

        if ($requiredHeaders !== null && $headerRow === null) {
            throw $this->invalid(
                "No se encontraron los encabezados esperados en las primeras 30 filas de {$sheetName}.",
                'LCD_CURRICULUM_XLSX_HEADERS_MISSING',
                [['sheet' => $sheetName, 'required_headers' => $requiredHeaders]],
            );
        }

        return $rows;
    }

    /**
     * @param  list<string>  $sharedStrings
     * @param  array<int, bool>  $dateStyles
     * @return array<int, mixed>
     */
    private function rowCells(
        string $xml,
        array $sharedStrings,
        array $dateStyles,
        string $sheetName,
        int $rowNumber,
    ): array {
        $document = $this->dom($xml, "{$sheetName} fila {$rowNumber}");
        $xpath = new DOMXPath($document);
        $cells = [];
        foreach ($xpath->query('//*[local-name()="c"]') ?: [] as $cell) {
            if (! $cell instanceof DOMElement) {
                continue;
            }
            $formula = $xpath->query('./*[local-name()="f"]', $cell);
            if ($formula && $formula->length > 0) {
                throw $this->invalid(
                    'Las fórmulas no están permitidas en el archivo curricular.',
                    'LCD_CURRICULUM_XLSX_FORMULA_NOT_ALLOWED',
                    [['sheet' => $sheetName, 'row' => $rowNumber, 'cell' => $cell->getAttribute('r')]],
                );
            }
            $column = $this->columnIndex($cell->getAttribute('r'));
            $type = $cell->getAttribute('t');
            $style = $cell->hasAttribute('s') ? (int) $cell->getAttribute('s') : null;
            $valueNode = $xpath->query('./*[local-name()="v"]', $cell)?->item(0);
            $raw = $valueNode?->textContent;
            $value = match ($type) {
                's' => $raw !== null ? ($sharedStrings[(int) $raw] ?? null) : null,
                'inlineStr' => $this->nodeTexts($xpath, $cell),
                'str' => $raw,
                'b' => $raw === '1',
                default => $this->scalar($raw, $style, $dateStyles),
            };
            if (is_string($value)) {
                $value = trim($value);
                if (mb_strlen($value) > self::MAX_CELL_CHARACTERS) {
                    throw $this->invalid(
                        'Una celda supera el máximo de caracteres permitido.',
                        'LCD_CURRICULUM_XLSX_CELL_SIZE_LIMIT',
                        [['sheet' => $sheetName, 'row' => $rowNumber, 'cell' => $cell->getAttribute('r')]],
                    );
                }
            }
            $cells[$column] = $value;
        }

        return $cells;
    }

    /** @param array<int, bool> $dateStyles */
    private function scalar(?string $raw, ?int $style, array $dateStyles): mixed
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if ($style !== null && ($dateStyles[$style] ?? false) && is_numeric($raw)) {
            return CarbonImmutable::create(1899, 12, 30, 0, 0, 0, 'UTC')
                ->addDays((int) floor((float) $raw))
                ->toDateString();
        }

        return is_numeric($raw) ? (float) $raw : $raw;
    }

    private function nodeTexts(DOMXPath $xpath, DOMElement $element): string
    {
        $texts = [];
        foreach ($xpath->query('.//*[local-name()="t"]', $element) ?: [] as $text) {
            $texts[] = $text->textContent;
        }

        return implode('', $texts);
    }

    private function textFromFragment(string $xml): string
    {
        $document = $this->dom($xml, 'shared string');
        $xpath = new DOMXPath($document);
        $texts = [];
        foreach ($xpath->query('//*[local-name()="t"]') ?: [] as $node) {
            $texts[] = $node->textContent;
        }

        return trim(implode('', $texts));
    }

    private function xmlReader(string $xml, string $label): XMLReader
    {
        $this->assertSafeXml($xml, $label);
        $reader = new XMLReader;
        if (! $reader->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw $this->invalid("No se pudo interpretar XML de {$label}.", 'LCD_CURRICULUM_XLSX_XML_INVALID');
        }

        return $reader;
    }

    private function dom(string $xml, string $label): DOMDocument
    {
        $this->assertSafeXml($xml, $label);
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                throw $this->invalid("No se pudo interpretar XML de {$label}.", 'LCD_CURRICULUM_XLSX_XML_INVALID');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $document;
    }

    private function assertSafeXml(string $xml, string $label): void
    {
        if (strlen($xml) > self::MAX_XML_BYTES) {
            throw $this->invalid("El XML de {$label} supera el límite permitido.", 'LCD_CURRICULUM_XLSX_XML_LIMIT');
        }
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw $this->invalid('Las entidades XML y DTD no están permitidas.', 'LCD_CURRICULUM_XLSX_XML_ENTITY_NOT_ALLOWED');
        }
    }

    private function columnIndex(string $reference): int
    {
        if (! preg_match('/^([A-Z]{1,3})\d+$/i', $reference, $matches)) {
            return 1;
        }
        $index = 0;
        foreach (str_split(strtoupper($matches[1])) as $letter) {
            $index = ($index * 26) + ord($letter) - 64;
        }

        return $index;
    }

    /** @param array<int, mixed> $cells */
    private function hasContent(array $cells): bool
    {
        return collect($cells)->contains(fn (mixed $value): bool => $value !== null && trim((string) $value) !== '');
    }

    private function key(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = Str::ascii(mb_strtolower(trim($value)));

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $value), '_');
    }

    /** @param array<int, array<string, mixed>> $details */
    private function invalid(string $message, string $code, array $details = []): LibroDigitalException
    {
        return new LibroDigitalException($message, $code, 422, $details);
    }
}
