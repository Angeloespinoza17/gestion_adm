<?php

namespace Tests\Unit\PedagogicalManagement;

use App\DTO\PedagogicalManagement\InstrumentAnalysisContext;
use App\Models\PedagogicalManagement\PedagogicalInstrument;
use App\Models\PedagogicalManagement\PedagogicalInstrumentFile;
use App\Services\PedagogicalManagement\FilenameTitleSimilarity;
use App\Services\PedagogicalManagement\InstrumentFieldExtractor;
use App\Services\PedagogicalManagement\Validation\ArithmeticValidationRule;
use App\Services\PedagogicalManagement\Validation\FilenameTitleValidationRule;
use App\Services\PedagogicalManagement\Validation\TechnicalPdfValidationRule;
use Tests\TestCase;

class DeterministicInstrumentAnalysisTest extends TestCase
{
    public function test_exact_named_components_reproduce_52_points_and_flag_declared_55(): void
    {
        $data = app(InstrumentFieldExtractor::class)->extract([$this->page(<<<'TEXT'
RÚBRICA INFOGRAFÍA DE CIENCIAS
OA 5
Puntaje total: 55 puntos
Resumen de puntajes
Investigación: 3 puntos
Infografía: 21 puntos
Aspectos formales: 12 puntos
Autoevaluación: 5 puntos
Coevaluación: 5 puntos
Metacognición: 6 puntos
TEXT)], 'Rubrica_Infografia_4Basico.pdf');

        $this->assertSame(52.0, $data['points']['computed_total_points']);
        $this->assertSame('exact', $data['points']['computation_reliability']);
        $this->assertSame([3.0, 21.0, 12.0, 5.0, 5.0, 6.0], array_column($data['points']['components'], 'value'));

        $instrument = new PedagogicalInstrument(['title' => 'Rúbrica infografía de ciencias', 'declared_total_points' => 55]);
        $file = new PedagogicalInstrumentFile(['original_filename' => 'Rubrica_Infografia_4Basico.pdf']);
        $findings = (new ArithmeticValidationRule())->validate(new InstrumentAnalysisContext($instrument, $file, $data, ''));
        $mismatch = collect($findings)->firstWhere('code', 'DECLARED_COMPUTED_TOTAL_MISMATCH');

        $this->assertSame('fail', $mismatch['outcome']);
        $this->assertTrue($mismatch['is_blocking']);
        $this->assertSame(['declared' => 55.0, 'computed' => 52.0, 'difference' => 3.0], $mismatch['detected_value']);
    }

    public function test_oa_notation_is_tolerant_but_never_infers_semantic_equivalence(): void
    {
        $data = app(InstrumentFieldExtractor::class)->extract([$this->page('OA01 · OA -9 10 · Objetivo de Aprendizaje: 6')], 'instrumento.pdf');

        $this->assertSame([1, 6, 9, 10], collect($data['learning_objectives'])->pluck('number')->unique()->sort()->values()->all());
        $this->assertSame(['OA 1', 'OA 10', 'OA 6', 'OA 9'], collect($data['learning_objectives'])->pluck('normalized_code')->unique()->sort()->values()->all());
    }

    public function test_probability_title_inside_function_filename_is_reported_as_lexical_mismatch(): void
    {
        $instrument = new PedagogicalInstrument(['title' => 'Control función seno y coseno']);
        $file = new PedagogicalInstrumentFile(['original_filename' => 'III Medio A Control Funcion Seno y Coseno.pdf']);
        $context = new InstrumentAnalysisContext($instrument, $file, [
            'title' => ['value' => 'Prueba de probabilidad condicionada', 'page' => 1, 'source_excerpt' => 'Prueba de probabilidad condicionada'],
        ], '');
        $finding = (new FilenameTitleValidationRule(app(FilenameTitleSimilarity::class)))->validate($context)[0];

        $this->assertSame('FILENAME_TITLE_MISMATCH', $finding['code']);
        $this->assertSame('warning', $finding['outcome']);
        $this->assertSame('heuristic', $finding['reliability']);
    }

    public function test_scanned_pdf_without_text_layer_is_not_analyzed_as_if_it_had_text(): void
    {
        $instrument = new PedagogicalInstrument(['title' => 'Instrumento escaneado']);
        $file = new PedagogicalInstrumentFile([
            'original_filename' => 'escaneado.pdf', 'mime_type' => 'application/pdf', 'file_size' => 2048,
            'is_encrypted' => false, 'has_text_layer' => false, 'page_count' => 1,
            'technical_metadata' => ['signature_valid' => true, 'integrity_checked' => true],
        ]);
        $findings = (new TechnicalPdfValidationRule())->validate(new InstrumentAnalysisContext($instrument, $file, ['text_character_count' => 0], ''));
        $noText = collect($findings)->firstWhere('code', 'PDF_NO_TEXT_LAYER');

        $this->assertSame('fail', $noText['outcome']);
        $this->assertTrue($noText['is_blocking']);
        $this->assertStringContainsString('no es evaluable automáticamente', $noText['message']);
        $this->assertStringNotContainsString('OCR', $noText['message']);
    }

    /** @return array{page_number:int,raw_text:string,normalized_text:string,has_images:bool,warnings:list<string>} */
    private function page(string $text): array
    {
        return ['page_number' => 1, 'raw_text' => $text, 'normalized_text' => $text, 'has_images' => false, 'warnings' => []];
    }
}
