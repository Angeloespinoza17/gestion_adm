<?php

namespace App\Services\LibroDigital;

use RuntimeException;
use Throwable;
use ZipArchive;

class CurriculumObjectivesXlsxBuilder
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  iterable<array-key, list<mixed>>  $objectives
     * @param  iterable<array-key, list<mixed>>  $sources
     */
    public function build(array $metadata, iterable $objectives, iterable $sources): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('La extensión ZIP requerida para XLSX no está disponible.');
        }

        $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'lcd-curriculum-'.bin2hex(random_bytes(8));
        if (! mkdir($directory, 0700) && ! is_dir($directory)) {
            throw new RuntimeException('No se pudo preparar el directorio XLSX temporal.');
        }
        $output = $directory.DIRECTORY_SEPARATOR.'curriculum.xlsx';
        $sheetFiles = [];
        $zip = new ZipArchive;
        $opened = false;

        try {
            $sheetFiles[] = $this->worksheet(
                $directory.DIRECTORY_SEPARATOR.'metadata.xml',
                ['Campo', 'Valor'],
                (function () use ($metadata): iterable {
                    foreach ($metadata as $key => $value) {
                        yield [(string) $key, $value];
                    }
                })(),
                [32, 95],
                [2],
                30,
            );
            $sheetFiles[] = $this->worksheet(
                $directory.DIRECTORY_SEPARATOR.'objectives.xml',
                $this->objectiveHeaders(),
                $objectives,
                [8, 28, 18, 12, 14, 12, 13, 16, 18, 24, 36, 18, 75, 105, 16, 24, 36, 16, 24, 66, 18, 18, 18, 62],
                [11, 13, 14, 24],
                null,
            );
            $sheetFiles[] = $this->worksheet(
                $directory.DIRECTORY_SEPARATOR.'sources.xml',
                $this->sourceHeaders(),
                $sources,
                [28, 18, 28, 20, 14, 28, 38, 20, 45, 24, 20, 46, 18, 22, 18, 66, 66, 14, 66, 18, 22, 66],
                [7, 9, 10, 11, 12, 14, 16, 17, 19, 22],
                42,
            );

            if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No se pudo abrir el contenedor XLSX curricular.');
            }
            $opened = true;
            foreach ([
                '[Content_Types].xml' => $this->contentTypes(),
                '_rels/.rels' => $this->packageRelationships(),
                'xl/workbook.xml' => $this->workbook(),
                'xl/_rels/workbook.xml.rels' => $this->workbookRelationships(),
                'xl/styles.xml' => $this->styles(),
            ] as $entry => $contents) {
                if ($zip->addFromString($entry, $contents) !== true) {
                    throw new RuntimeException('No se pudo incorporar '.$entry.' al XLSX curricular.');
                }
            }
            foreach ($sheetFiles as $index => $sheetFile) {
                if (! $zip->addFile($sheetFile, 'xl/worksheets/sheet'.($index + 1).'.xml')) {
                    throw new RuntimeException('No se pudo incorporar una hoja al XLSX curricular.');
                }
            }
            if ($zip->close() !== true) {
                throw new RuntimeException('No se pudo cerrar íntegramente el XLSX curricular.');
            }
            $opened = false;

            $this->assertValidPackage($output);

            $contents = file_get_contents($output);
            if ($contents === false) {
                throw new RuntimeException('No se pudo leer el XLSX curricular generado.');
            }

            return $contents;
        } finally {
            if ($opened) {
                $zip->close();
            }
            foreach ($sheetFiles as $sheetFile) {
                @unlink($sheetFile);
            }
            @unlink($output);
            @rmdir($directory);
        }
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<array-key, list<mixed>>  $rows
     * @param  list<float|int>  $widths
     * @param  list<int>  $wrapColumns
     */
    private function worksheet(string $path, array $headers, iterable $rows, array $widths, array $wrapColumns, ?int $rowHeight): string
    {
        $stream = fopen($path, 'wb');
        if (! is_resource($stream)) {
            throw new RuntimeException('No se pudo abrir una hoja XLSX temporal.');
        }

        try {
            $this->write($stream, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
                .'<sheetFormatPr defaultRowHeight="15"/><cols>');
            foreach ($widths as $index => $width) {
                $column = $index + 1;
                $width = max(6, min(120, (float) $width));
                $this->write($stream, '<col min="'.$column.'" max="'.$column.'" width="'.$width.'" customWidth="1"/>');
            }
            $this->write($stream, '</cols><sheetData>'.$this->row(1, $headers, $wrapColumns, true, 24));

            $rowNumber = 2;
            foreach ($rows as $row) {
                $values = array_values($row);
                $height = $rowHeight ?? $this->estimatedRowHeight($values, $widths, $wrapColumns);
                $this->write($stream, $this->row($rowNumber++, $values, $wrapColumns, false, $height));
            }
            $lastColumn = $this->columnName(count($headers));
            $lastRow = max(1, $rowNumber - 1);
            $this->write($stream, '</sheetData><autoFilter ref="A1:'.$lastColumn.$lastRow.'"/></worksheet>');
        } catch (Throwable $exception) {
            fclose($stream);
            @unlink($path);

            throw $exception;
        }
        fclose($stream);

        return $path;
    }

    /** @param resource $stream */
    private function write($stream, string $contents): void
    {
        $offset = 0;
        $length = strlen($contents);
        while ($offset < $length) {
            $written = fwrite($stream, substr($contents, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('No se pudo escribir una hoja XLSX temporal.');
            }
            $offset += $written;
        }
    }

    /** @param list<mixed> $values @param list<int> $wrapColumns */
    private function row(int $number, array $values, array $wrapColumns, bool $header, int $height): string
    {
        $cells = '';
        foreach ($values as $index => $value) {
            $column = $index + 1;
            $cells .= $this->cell($this->columnName($column).$number, $value, $header, in_array($column, $wrapColumns, true));
        }

        return '<row r="'.$number.'" ht="'.$height.'" customHeight="1">'.$cells.'</row>';
    }

    private function cell(string $reference, mixed $value, bool $header, bool $wrapped): string
    {
        $style = $header ? ' s="1"' : ($wrapped ? ' s="2"' : '');
        if (! $header && (is_int($value) || is_float($value)) && is_finite((float) $value)) {
            return '<c r="'.$reference.'"'.$style.'><v>'.$value.'</v></c>';
        }

        $text = is_bool($value) ? ($value ? 'Sí' : 'No') : (string) ($value ?? '');
        if (mb_strlen($text) > 32767) {
            throw new RuntimeException("La celda {$reference} supera el máximo XLSX de 32.767 caracteres; no se truncó el contenido oficial.");
        }
        if (preg_match('/^[\x00-\x20]*[=+\-@]/', $text)) {
            $text = "'".$text;
        }

        return '<c r="'.$reference.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.$this->xml($text).'</t></is></c>';
    }

    /** @return list<string> */
    private function objectiveHeaders(): array
    {
        return [
            'ID interno', 'ID público', 'Clave objetiva SHA-256', 'Código', 'Tipo', 'Estado', 'Nivel', 'Grado',
            'Tipo de enseñanza', 'Código asignatura', 'Asignatura', 'Eje', 'Descripción oficial', 'Indicadores',
            'Unidad', 'Página / localizador', 'Código catálogo', 'Versión catálogo', 'Autoridad', 'Hash catálogo',
            'N° fuentes', 'Fuentes canónicas', 'Fuentes verificadas', 'Resumen de fuentes',
        ];
    }

    /** @return list<string> */
    private function sourceHeaders(): array
    {
        return [
            'ID público objetivo', 'Código objetivo', 'Clave objetiva SHA-256', 'Asignatura', 'Grado',
            'ID público fuente', 'Clave fuente', 'Alcance', 'Nombre fuente', 'Autoridad', 'Documento', 'URL pública',
            'Rol', 'Localizador', 'Estado fuente', 'SHA-256 declarado', 'SHA-256 verificado', 'Hashes coinciden',
            'Hash relación', 'Estado evidencia', 'Evidencia verificada', 'SHA-256 evidencia',
        ];
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>';
    }

    private function packageRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function assertValidPackage(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException('El XLSX curricular generado no es un contenedor OOXML íntegro.');
        }
        try {
            foreach ([
                '[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels',
                'xl/styles.xml', 'xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml', 'xl/worksheets/sheet3.xml',
            ] as $entry) {
                if ($zip->locateName($entry) === false) {
                    throw new RuntimeException('El XLSX curricular no contiene la pieza requerida '.$entry.'.');
                }
            }
        } finally {
            if ($zip->close() !== true) {
                throw new RuntimeException('No se pudo cerrar la validación del XLSX curricular.');
            }
        }
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'
            .'<sheet name="Metadatos" sheetId="1" r:id="rId1"/><sheet name="Objetivos" sheetId="2" r:id="rId2"/><sheet name="Fuentes" sheetId="3" r:id="rId3"/></sheets></workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>'
            .'<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="10"/><name val="Arial"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="10"/><name val="Arial"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF405189"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs></styleSheet>';
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    /** @param list<mixed> $values @param list<float|int> $widths @param list<int> $wrapColumns */
    private function estimatedRowHeight(array $values, array $widths, array $wrapColumns): int
    {
        $lines = 1;
        foreach ($wrapColumns as $column) {
            $text = (string) ($values[$column - 1] ?? '');
            $width = max(8, (float) ($widths[$column - 1] ?? 20));
            $charactersPerLine = max(8, (int) floor($width * 1.25));
            $estimated = 0;
            foreach (preg_split('/\R/u', $text) ?: [''] as $paragraph) {
                $estimated += max(1, (int) ceil(mb_strlen($paragraph) / $charactersPerLine));
            }
            $lines = max($lines, $estimated);
        }

        // Excel caps row height at 409.5 pt. The longest official description
        // (2,326 characters in the validated corpus) remains fully visible at
        // the configured description width while short rows stay compact.
        return min(409, max(30, 6 + ($lines * 15)));
    }

    private function xml(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8');
    }
}
