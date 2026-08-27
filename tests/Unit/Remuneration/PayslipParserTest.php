<?php

namespace Tests\Unit\Remuneration;

use App\Services\Remuneration\Payslips\NumerusPayslipParser;
use App\Services\Remuneration\Payslips\PayslipDistributionService;
use App\Services\Remuneration\Payslips\PayslipPdfTextExtractor;
use App\Services\Remuneration\Payslips\PayslipProviderDetector;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PayslipParserTest extends TestCase
{
    public function test_numerus_parser_detects_dynamic_sources_wrapped_concepts_and_all_sections(): void
    {
        $parser = app(NumerusPayslipParser::class);
        $page = $this->numerusPage();

        $this->assertSame('numerus', app(PayslipProviderDetector::class)->detect($page)->provider());
        $parsed = $parser->parse($page);

        $this->assertSame(['year' => 2026, 'month' => 7], $parsed['period']);
        $this->assertSame('123456785', $parsed['employee']['rut']);
        $this->assertSame(['SEP', 'GENERAL'], $parsed['funding_labels']);
        $this->assertCount(3, $parsed['earnings']);
        $this->assertSame('ASIGNACION EXTENDIDA EN DOS LINEAS', $parsed['earnings'][1]['description']);
        $this->assertFalse($parsed['earnings'][2]['is_imponible']);
        $this->assertSame(['legal_previsional', 'other'], array_column($parsed['discounts'], 'classification'));
        $this->assertSame(['Previred', 'Otras retenciones'], array_column($parsed['discounts'], 'payment_destination'));
        $this->assertCount(2, $parsed['employer_contributions']);
        $this->assertSame([
            'gross_taxable' => 300,
            'gross_non_taxable' => 50,
            'gross_total' => 350,
            'legal_deductions' => 30,
            'other_deductions' => 20,
            'total_deductions' => 50,
            'net_amount' => 300,
            'employer_contributions' => 30,
        ], $parsed['totals']);
        $this->assertSame([], collect($parsed['warnings'])->where('severity', 'error')->values()->all());
    }

    public function test_provider_detector_rejects_unknown_layout(): void
    {
        $this->expectException(ValidationException::class);
        app(PayslipProviderDetector::class)->detect([
            'text' => 'Documento sin formato conocido',
            'tokens' => [],
            'page_number' => 1,
            'extraction_method' => 'native',
        ]);
    }

    public function test_native_text_extractor_separates_a_multi_page_pdf(): void
    {
        $path = $this->twoPagePdf();
        try {
            $pages = app(PayslipPdfTextExtractor::class)->extract($path);
            $this->assertCount(2, $pages);
            $this->assertSame([1, 2], array_column($pages, 'page_number'));
            $this->assertStringContainsString('PAGINA UNO', $pages[0]['text']);
            $this->assertStringContainsString('PAGINA DOS', $pages[1]['text']);
            $this->assertSame(['native', 'native'], array_column($pages, 'extraction_method'));
        } finally {
            @unlink($path);
        }
    }

    public function test_distribution_uses_taxable_and_gross_bases_and_absorbs_rounding_residual(): void
    {
        $distributed = app(PayslipDistributionService::class)->distribute([
            'funding_labels' => ['SEP', 'GENERAL', 'PIE'],
            'earnings' => [
                ['funding_label' => 'SEP', 'is_imponible' => true, 'amount' => 1],
                ['funding_label' => 'GENERAL', 'is_imponible' => true, 'amount' => 1],
                ['funding_label' => 'PIE', 'is_imponible' => true, 'amount' => 1],
                ['funding_label' => 'PIE', 'is_imponible' => false, 'amount' => 2],
            ],
            'discounts' => [
                ['classification' => 'legal_previsional', 'amount' => 2],
                ['classification' => 'other', 'amount' => 2],
            ],
            'employer_contributions' => [
                ['funding_label' => 'SEP', 'amount' => 1],
                ['funding_label' => 'GENERAL', 'amount' => 2],
                ['funding_label' => 'PIE', 'amount' => 3],
            ],
            'totals' => [
                'gross_taxable' => 3,
                'gross_non_taxable' => 2,
                'gross_total' => 5,
                'total_deductions' => 4,
                'net_amount' => 1,
                'employer_contributions' => 6,
            ],
        ]);

        $legal = collect($distributed['discount_allocations'])->where('discount_index', 0);
        $other = collect($distributed['discount_allocations'])->where('discount_index', 1);
        $this->assertSame(2, (int) $legal->sum('assigned_amount'));
        $this->assertSame(2, (int) $other->sum('assigned_amount'));
        $this->assertSame(-1, (int) $legal->last()['rounding_adjustment']);
        $this->assertSame(1, (int) collect($distributed['summaries'])->sum('net_amount'));
        $this->assertSame(6, (int) collect($distributed['summaries'])->sum('employer_contributions'));
        $this->assertTrue(collect($distributed['controls'])->every(fn (array $control): bool => $control['status'] === 'correcto'));
    }

    public function test_distribution_reports_zero_base_and_unclassified_discounts_without_silent_adjustment(): void
    {
        $distributed = app(PayslipDistributionService::class)->distribute([
            'funding_labels' => ['GENERAL'],
            'earnings' => [],
            'discounts' => [
                ['classification' => 'legal_previsional', 'amount' => 10],
                ['classification' => 'pending', 'amount' => 5],
            ],
            'employer_contributions' => [],
            'totals' => ['gross_total' => 0, 'total_deductions' => 15, 'net_amount' => -15, 'employer_contributions' => 0],
        ]);

        $this->assertEqualsCanonicalizing(
            ['invalid_taxable_base', 'invalid_gross_base', 'unclassified_discount'],
            array_column($distributed['issues'], 'code'),
        );
        $this->assertSame(0, collect($distributed['discount_allocations'])->sum('assigned_amount'));
        $this->assertSame('error', collect($distributed['controls'])->firstWhere('code', 'distributed_discounts')['status']);
    }

    public function test_single_funding_source_receives_the_exact_net_without_rounding_drift(): void
    {
        $distributed = app(PayslipDistributionService::class)->distribute([
            'funding_labels' => ['GENERAL'],
            'earnings' => [
                ['funding_label' => 'GENERAL', 'is_imponible' => true, 'amount' => 1000],
                ['funding_label' => 'GENERAL', 'is_imponible' => false, 'amount' => 100],
            ],
            'discounts' => [
                ['classification' => 'legal_previsional', 'amount' => 120],
                ['classification' => 'other', 'amount' => 30],
            ],
            'employer_contributions' => [['funding_label' => 'GENERAL', 'amount' => 50]],
            'totals' => [
                'gross_taxable' => 1000,
                'gross_non_taxable' => 100,
                'gross_total' => 1100,
                'total_deductions' => 150,
                'net_amount' => 950,
                'employer_contributions' => 50,
            ],
        ]);

        $this->assertSame(950, $distributed['summaries'][0]['net_amount']);
        $this->assertSame(150, (int) collect($distributed['discount_allocations'])->sum('assigned_amount'));
        $this->assertSame(0, (int) collect($distributed['discount_allocations'])->sum('rounding_adjustment'));
    }

    public function test_reference_pdf_regression_when_fixture_is_explicitly_available(): void
    {
        $path = getenv('NUMERUS_REFERENCE_PDF') ?: '';
        if ($path === '' || ! is_file($path)) {
            $this->markTestSkipped('Defina NUMERUS_REFERENCE_PDF para ejecutar la regresión privada de 123 páginas.');
        }

        $pages = app(PayslipPdfTextExtractor::class)->extract($path);
        $parser = app(NumerusPayslipParser::class);
        $parsed = collect($pages)->map(fn (array $page): array => $parser->parse($page));
        $distributed = $parsed->map(fn (array $row): array => app(PayslipDistributionService::class)->distribute($row));
        $netBySource = $distributed
            ->flatMap(fn (array $row): array => $row['summaries'])
            ->groupBy('funding_label')
            ->map(fn ($rows): int => (int) $rows->sum('net_amount'));

        $this->assertCount(123, $pages);
        $this->assertSame(897, $parsed->sum(fn (array $row): int => count($row['earnings'])));
        $this->assertSame(794, $parsed->sum(fn (array $row): int => count($row['discounts'])));
        $this->assertSame(1158, $parsed->sum(fn (array $row): int => count($row['employer_contributions'])));
        $this->assertSame(173165191, (int) $parsed->sum('totals.gross_total'));
        $this->assertSame(171232035, (int) $parsed->sum('totals.gross_taxable'));
        $this->assertSame(46843817, (int) $parsed->sum('totals.total_deductions'));
        $this->assertSame(126321374, (int) $parsed->sum('totals.net_amount'));
        $this->assertSame(9420969, (int) $parsed->sum('totals.employer_contributions'));
        $this->assertSame(97735213, $netBySource->get('GENERAL'));
        $this->assertSame(19189965, $netBySource->get('SEP'));
        $this->assertSame(9396196, $netBySource->get('PIE'));
        $this->assertSame(0, $parsed->flatMap(fn (array $row): array => $row['warnings'])->where('severity', 'error')->count());
        $this->assertSame(0, $distributed->flatMap(fn (array $row): array => $row['controls'])->where('status', 'error')->count());
    }

    /** @return array<string,mixed> */
    private function numerusPage(): array
    {
        $tokens = [
            $this->token(20, 760, 'RBD'), $this->token(20, 750, '1-9'),
            $this->token(150, 760, 'TRABAJADOR'), $this->token(150, 750, 'PERSONA DE PRUEBA'),
            $this->token(350, 760, 'RUT TRABAJADOR'), $this->token(350, 750, '12.345.678-5'),
            $this->token(20, 500, 'HABERES'), $this->token(200, 500, 'SEP'), $this->token(300, 500, 'GENERAL'), $this->token(550, 500, 'TOTAL'),
            $this->token(20, 480, '(1000) SUELDO BASE'), $this->token(200, 480, '$ 100'),
            $this->token(20, 470, '(1001) ASIGNACION EXTENDIDA'), $this->token(20, 466, 'EN DOS LINEAS'), $this->token(300, 470, '$ 200'),
            $this->token(20, 455, 'TOTAL IMPONIBLE'), $this->token(200, 455, '$ 100'), $this->token(300, 455, '$ 200'), $this->token(550, 455, '$ 300'),
            $this->token(20, 445, '(3000) MOVILIZACION'), $this->token(300, 445, '$ 50'),
            $this->token(20, 435, 'TOTAL NO IMPONIBLE'), $this->token(200, 435, '$ 0'), $this->token(300, 435, '$ 50'), $this->token(550, 435, '$ 50'),
            $this->token(20, 425, 'TOTAL HABERES'), $this->token(200, 425, '$ 100'), $this->token(300, 425, '$ 250'), $this->token(550, 425, '$ 350'),
            $this->token(20, 400, 'DESCUENTOS'),
            $this->token(20, 380, '(2000) PREVISION'), $this->token(200, 380, '$ 30'),
            $this->token(20, 370, 'TOTAL DESC LEGALES'), $this->token(550, 370, '$ 30'),
            $this->token(20, 350, '(3001) CUOTA INTERNA'), $this->token(200, 350, '$ 20'),
            $this->token(20, 340, 'TOTAL DESC OTROS'), $this->token(550, 340, '$ 20'),
            $this->token(20, 330, 'TOTAL DESCUENTOS'), $this->token(550, 330, '$ 50'),
            $this->token(20, 300, 'ALCANCE LIQUIDO'), $this->token(550, 300, '$ 300'),
            $this->token(20, 250, 'APORTE EMPLEADOR LEYES SOCIALES'),
            $this->token(200, 245, 'SEP'), $this->token(300, 245, 'GENERAL'), $this->token(550, 245, 'TOTAL'),
            $this->token(20, 230, 'SIS'), $this->token(200, 230, '$ 10'), $this->token(300, 230, '$ 20'),
            $this->token(20, 220, 'TOTAL'), $this->token(200, 220, '$ 10'), $this->token(300, 220, '$ 20'), $this->token(550, 220, '$ 30'),
        ];

        return [
            'text' => 'remuneraciones.numerus.cl LIQUIDACIÓN DE SUELDO JULIO 2026 ALCANCE LÍQUIDO APORTE EMPLEADOR LEYES SOCIALES',
            'tokens' => $tokens,
            'page_number' => 1,
            'extraction_method' => 'native',
            'confidence' => 1.0,
        ];
    }

    /** @return array{x:float,y:float,text:string} */
    private function token(float $x, float $y, string $text): array
    {
        return compact('x', 'y', 'text');
    }

    private function twoPagePdf(): string
    {
        $firstStream = 'BT /F1 12 Tf 50 700 Td (LIQUIDACION DE SUELDO PAGINA UNO TEXTO NATIVO DE PRUEBA) Tj ET';
        $secondStream = 'BT /F1 12 Tf 50 700 Td (LIQUIDACION DE SUELDO PAGINA DOS TEXTO NATIVO DE PRUEBA) Tj ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R 5 0 R] /Count 2 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 7 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($firstStream).">>\nstream\n{$firstStream}\nendstream",
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 7 0 R >> >> /Contents 6 0 R >>',
            '<< /Length '.strlen($secondStream).">>\nstream\n{$secondStream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 8\n0000000000 65535 f \n";
        for ($index = 1; $index <= 7; $index++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$index])."\n";
        }
        $pdf .= "trailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
        $path = tempnam(sys_get_temp_dir(), 'payslip-pdf-');
        file_put_contents($path, $pdf);

        return $path;
    }
}
