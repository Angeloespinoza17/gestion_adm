<?php

namespace Tests\Unit\LibroDigital;

use App\Services\Attendance\AttendancePdfBuilder;
use PHPUnit\Framework\TestCase;

class AttendancePdfBuilderTest extends TestCase
{
    public function test_official_report_contains_trace_pagination_and_draft_watermark(): void
    {
        $pdf = (new AttendancePdfBuilder)->build(
            'Informe Libro Digital',
            ['periodo' => '2026', 'año académico' => '2026', 'tipo de reporte' => 'Asistencia', 'fecha' => '12-08-2026', 'generado por' => 'Prueba'],
            [['title' => 'Resumen ejecutivo', 'headers' => ['Indicador', 'Valor'], 'rows' => [['Asistencia', '100 %'], ['Presentes', 2], ['Ausentes', 0]]]],
            ['branding' => ['organization_name' => 'Colegio de Prueba', 'report_trace' => 'RBD 1234 · ID 01TEST', 'source_label' => 'snapshot abc', 'watermark' => 'Borrador']],
        );

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('(BORRADOR)', $pdf);
        $this->assertStringContainsString('RBD 1234', $pdf);
        $this->assertStringContainsString("P\xE1gina 1 de 1", $pdf);
    }
}
