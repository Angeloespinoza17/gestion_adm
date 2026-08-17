<?php

namespace Tests\Unit\LibroDigital;

use App\Services\LibroDigital\XlsxReportBuilder;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class XlsxReportBuilderTest extends TestCase
{
    public function test_it_generates_a_valid_ooxml_container_and_neutralizes_formulas(): void
    {
        $contents = (new XlsxReportBuilder)->build(
            ['RBD' => '1234'],
            [['title' => 'Asistencia', 'headers' => ['Estudiante', 'Estado'], 'rows' => [['=HYPERLINK("bad")', 'Presente']]]],
        );
        $this->assertStringStartsWith('PK', $contents);

        $path = tempnam(sys_get_temp_dir(), 'lcd-xlsx-test-');
        file_put_contents($path, $contents);
        $zip = new ZipArchive;
        try {
            $this->assertTrue($zip->open($path) === true);
            $this->assertNotFalse($zip->locateName('[Content_Types].xml'));
            $sheet = $zip->getFromName('xl/worksheets/sheet2.xml');
            $this->assertIsString($sheet);
            $this->assertStringContainsString('&apos;=HYPERLINK', $sheet);
        } finally {
            $zip->close();
            @unlink($path);
        }
    }
}
