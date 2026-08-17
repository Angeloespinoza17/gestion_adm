<?php

namespace App\Services\LibroDigital;

use RuntimeException;
use ZipArchive;

class XlsxReportBuilder
{
    public function build(array $metadata, array $sections): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('La extensión ZIP requerida para XLSX no está disponible.');
        }

        $worksheets = [[
            'title' => 'Metadatos',
            'headers' => ['Campo', 'Valor'],
            'rows' => collect($metadata)->map(fn ($value, $key) => [$key, $value])->values()->all(),
            'column_widths' => [32, 95],
            'wrap_columns' => [2],
            'row_height' => 30,
        ], ...$sections];
        $names = $this->sheetNames($worksheets);
        $temporary = tempnam(sys_get_temp_dir(), 'lcd-xlsx-');
        if ($temporary === false) {
            throw new RuntimeException('No se pudo crear el archivo XLSX temporal.');
        }

        $zip = new ZipArchive;
        $opened = false;
        try {
            if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No se pudo abrir el contenedor XLSX.');
            }
            $opened = true;
            $zip->addFromString('[Content_Types].xml', $this->contentTypes(count($worksheets)));
            $zip->addFromString('_rels/.rels', $this->packageRelationships());
            $zip->addFromString('xl/workbook.xml', $this->workbook($names));
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships(count($worksheets)));
            $zip->addFromString('xl/styles.xml', $this->styles());
            foreach ($worksheets as $index => $worksheet) {
                $zip->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', $this->worksheet($worksheet));
            }
            $zip->close();
            $opened = false;
            $contents = file_get_contents($temporary);
            if ($contents === false) {
                throw new RuntimeException('No se pudo leer el XLSX generado.');
            }

            return $contents;
        } finally {
            if ($opened) {
                $zip->close();
            }
            @unlink($temporary);
        }
    }

    private function worksheet(array $section): string
    {
        $headers = array_values((array) ($section['headers'] ?? []));
        $rows = collect($section['rows'] ?? [])->map(fn ($row) => array_values((array) $row))->all();
        $allRows = [$headers, ...$rows];
        $columnCount = max(1, ...array_map('count', $allRows));
        $lastCell = $this->columnName($columnCount).'1';
        $wrapColumns = collect($section['wrap_columns'] ?? [])->map(fn ($column): int => (int) $column)->all();
        $dataHeight = max(15, min(180, (int) ($section['row_height'] ?? 15)));
        $xmlRows = '';
        foreach ($allRows as $rowIndex => $row) {
            $number = $rowIndex + 1;
            $cells = '';
            for ($column = 1; $column <= $columnCount; $column++) {
                $cells .= $this->cell(
                    $this->columnName($column).$number,
                    $row[$column - 1] ?? '',
                    $rowIndex === 0,
                    in_array($column, $wrapColumns, true),
                );
            }
            $height = $rowIndex > 0 && $dataHeight > 15 ? ' ht="'.$dataHeight.'" customHeight="1"' : '';
            $xmlRows .= '<row r="'.$number.'"'.$height.'>'.$cells.'</row>';
        }

        $columns = $this->columns((array) ($section['column_widths'] ?? []), $columnCount);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .$columns
            .'<sheetData>'.$xmlRows.'</sheetData>'
            .'<autoFilter ref="A1:'.$lastCell.'"/>'
            .'</worksheet>';
    }

    private function cell(string $reference, mixed $value, bool $header, bool $wrapped = false): string
    {
        $style = $header ? ' s="1"' : ($wrapped ? ' s="2"' : '');
        if (! $header && (is_int($value) || is_float($value)) && is_finite((float) $value)) {
            return '<c r="'.$reference.'"'.$style.'><v>'.$value.'</v></c>';
        }
        $text = is_bool($value) ? ($value ? 'Sí' : 'No') : (string) ($value ?? '');
        if (preg_match('/^[=+\-@]/', $text)) {
            $text = "'".$text;
        }

        return '<c r="'.$reference.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.$this->xml($text).'</t></is></c>';
    }

    private function contentTypes(int $sheets): string
    {
        $overrides = '';
        for ($index = 1; $index <= $sheets; $index++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$index.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$overrides.'</Types>';
    }

    private function packageRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(array $names): string
    {
        $sheets = collect($names)->map(fn ($name, $index) => '<sheet name="'.$this->xml($name).'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>')->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets></workbook>';
    }

    private function workbookRelationships(int $sheets): string
    {
        $relationships = '';
        for ($index = 1; $index <= $sheets; $index++) {
            $relationships .= '<Relationship Id="rId'.$index.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$index.'.xml"/>';
        }
        $relationships .= '<Relationship Id="rId'.($sheets + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="10"/><name val="Arial"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="10"/><name val="Arial"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF405189"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs>'
            .'</styleSheet>';
    }

    private function columns(array $widths, int $columnCount): string
    {
        if ($widths === []) {
            return '';
        }

        $columns = '';
        for ($column = 1; $column <= $columnCount; $column++) {
            $width = max(6, min(120, (float) ($widths[$column - 1] ?? 18)));
            $columns .= '<col min="'.$column.'" max="'.$column.'" width="'.$width.'" customWidth="1"/>';
        }

        return '<cols>'.$columns.'</cols>';
    }

    private function sheetNames(array $worksheets): array
    {
        $used = [];

        return collect($worksheets)->map(function ($sheet, $index) use (&$used): string {
            $base = mb_substr(trim((string) preg_replace('~[\\/?*\[\]:]+~u', ' ', (string) ($sheet['title'] ?? 'Hoja '.($index + 1)))), 0, 31) ?: 'Hoja '.($index + 1);
            $name = $base;
            $suffix = 2;
            while (in_array(mb_strtolower($name), $used, true)) {
                $ending = ' '.$suffix++;
                $name = mb_substr($base, 0, 31 - mb_strlen($ending)).$ending;
            }
            $used[] = mb_strtolower($name);

            return $name;
        })->all();
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

    private function xml(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8');
    }
}
