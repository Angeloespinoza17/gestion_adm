<?php

namespace Tests\Unit\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Services\LibroDigital\CurriculumXlsxReader;
use App\Services\LibroDigital\XlsxReportBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ZipArchive;

class CurriculumXlsxReaderTest extends TestCase
{
    public function test_real_downloadable_template_is_read_with_headers_after_help_rows(): void
    {
        $reader = new CurriculumXlsxReader;
        $workbook = $reader->read(__DIR__.'/../../../resources/templates/libro-digital/plantilla-importacion-curriculo-nt1-4m.xlsx');

        $this->assertCount(1, $workbook['catalogs']);
        $this->assertSame('Ministerio de Educación de Chile', $workbook['catalogs'][0]['authority']);
        $this->assertArrayHasKey('curriculum_track', $workbook['objectives'][0]);
        $this->assertSame('6830', (string) $workbook['links'][0]['school_rbd']);
        $this->assertNotEmpty($workbook['references']);
    }

    public function test_secure_reader_rejects_formula_cells(): void
    {
        $contents = (new XlsxReportBuilder)->build([], [
            [
                'title' => 'Catalogo',
                'headers' => ['catalog_code', 'catalog_name', 'version', 'authority', 'source_url', 'source_sha256', 'effective_from', 'effective_to'],
                'rows' => [['CAT', 'Catálogo', '1', 'MINEDUC', 'https://example.test', str_repeat('a', 64), '', '']],
            ],
            [
                'title' => 'Objetivos',
                'headers' => ['catalog_code', 'catalog_version', 'code', 'objective_type', 'subject_code', 'level_code', 'grade_code', 'curriculum_track', 'axis_code', 'unit_code', 'description', 'indicators_json', 'active', 'source_page'],
                'rows' => [['CAT', '1', 'OA1', 'OA', '', 'PARVULARIA', 'NT1', '', 'N', '', 'Texto', '[]', 'SI', '']],
            ],
            [
                'title' => 'Vinculos',
                'headers' => ['school_rbd', 'academic_year', 'subject_code', 'catalog_code', 'catalog_version', 'level_code', 'grade_code', 'curriculum_track', 'valid_from', 'valid_to', 'active'],
                'rows' => [['6830', '2026', 'LEN', 'CAT', '1', 'BASICA', '5B', '', '', '', 'SI']],
            ],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'lcd-curr-reader-');
        file_put_contents($path, $contents);
        $zip = new ZipArchive;
        $open = false;
        try {
            $this->assertTrue($zip->open($path) === true);
            $open = true;
            $sheet = (string) $zip->getFromName('xl/worksheets/sheet2.xml');
            $sheet = preg_replace(
                '/<c r="A2"[^>]*>.*?<\/c>/',
                '<c r="A2"><f>1+1</f><v>2</v></c>',
                $sheet,
                1,
            );
            $this->assertIsString($sheet);
            $zip->addFromString('xl/worksheets/sheet2.xml', $sheet);
            $zip->close();
            $open = false;

            $this->expectException(LibroDigitalException::class);
            $this->expectExceptionMessage('Las fórmulas no están permitidas');
            (new CurriculumXlsxReader)->read($path);
        } finally {
            if ($open) {
                $zip->close();
            }
            @unlink($path);
        }
    }

    public function test_sheet_row_limit_accepts_fifteen_thousand_and_rejects_the_next_row(): void
    {
        $reader = new CurriculumXlsxReader;
        $parseSheet = new ReflectionMethod($reader, 'parseSheet');

        $accepted = $parseSheet->invoke(
            $reader,
            $this->singleColumnSheetXml(15_000),
            'Objetivos',
            ['code'],
            [],
            [],
        );
        $this->assertCount(15_000, $accepted);

        try {
            $parseSheet->invoke(
                $reader,
                $this->singleColumnSheetXml(15_001),
                'Objetivos',
                ['code'],
                [],
                [],
            );
            $this->fail('La fila 15.001 debía superar el límite seguro por hoja.');
        } catch (LibroDigitalException $exception) {
            $this->assertSame('LCD_CURRICULUM_XLSX_ROW_LIMIT', $exception->errorCode);
            $this->assertStringContainsString('supera el límite de filas', $exception->getMessage());
        }
    }

    private function singleColumnSheetXml(int $dataRows): string
    {
        $rows = [
            '<row r="1"><c r="A1" t="inlineStr"><is><t>code</t></is></c></row>',
        ];
        for ($index = 1; $index <= $dataRows; $index++) {
            $row = $index + 1;
            $rows[] = "<row r=\"{$row}\"><c r=\"A{$row}\" t=\"inlineStr\"><is><t>OA{$index}</t></is></c></row>";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .implode('', $rows)
            .'</sheetData></worksheet>';
    }
}
