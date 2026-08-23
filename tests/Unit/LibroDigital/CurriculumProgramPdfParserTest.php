<?php

namespace Tests\Unit\LibroDigital;

use App\Services\LibroDigital\Curriculum\ProgramStudyDocumentParser;
use App\Services\LibroDigital\Curriculum\SmalotPdfTextExtractor;
use Tests\TestCase;

class CurriculumProgramPdfParserTest extends TestCase
{
    public function test_real_mineduc_fixture_preserves_program_structure_and_page_evidence(): void
    {
        $fixture = '/Users/angeloespinozarodriguez/Desktop/articles-20714_programa.pdf';
        if (! is_file($fixture)) {
            $this->markTestSkipped('El fixture ministerial local no está disponible.');
        }

        $extracted = app(SmalotPdfTextExtractor::class)->extract($fixture);
        $parsed = app(ProgramStudyDocumentParser::class)->parse([
            'document_type' => 'program_study',
            'title' => 'Ciencias Naturales - Programa de Estudio - Primer Año Básico',
        ], $extracted['pages']);

        $this->assertCount(184, $extracted['pages']);
        $this->assertSame(38, $parsed['summary']['estimated_weeks']);
        $this->assertSame(114, $parsed['summary']['estimated_pedagogical_hours']);
        $this->assertSame(4, $parsed['summary']['unit_count']);
        $this->assertSame(12, $parsed['summary']['thematic_objective_count']);
        $this->assertSame(4, $parsed['summary']['skill_objective_count']);
        $this->assertSame(3, $parsed['summary']['axis_count']);

        $units = collect($parsed['candidates'])->where('entity_type', 'unit')->values();
        $this->assertSame([30, 30, 30, 24], $units->pluck('structured_payload.estimated_pedagogical_hours')->all());
        $this->assertSame([57, 79, 99, 121], $units->pluck('physical_page')->all());
        $this->assertSame(['55', '77', '97', '119'], $units->pluck('printed_page')->all());
        $this->assertSame(
            ['OA 1', 'OA 2', 'OA 3', 'OA 4', 'OA 5', 'OA 6', 'OA 7', 'OA 8', 'OA 9', 'OA 10', 'OA 11', 'OA 12'],
            collect($parsed['candidates'])->where('entity_type', 'learning_objective')->pluck('structured_payload.official_code')->values()->all(),
        );
        $objectives = collect($parsed['candidates'])->where('entity_type', 'learning_objective')->keyBy('candidate_key');
        $this->assertSame(
            'Reconocer y observar, por medio de la exploración, que los seres vivos crecen, responden a estímulos del medio, se reproducen y necesitan agua, alimento y aire para vivir, comparándolos con las cosas no vivas.',
            data_get($objectives, 'objective:OA:1.structured_payload.official_text'),
        );
        $this->assertSame(
            'Describir y registrar el ciclo diario y las diferencias entre el día y la noche, a partir de la observación del Sol, la Luna, las estrellas y la luminosidad del cielo, entre otras, y sus efectos en los seres vivos y el ambiente.',
            data_get($objectives, 'objective:OA:11.structured_payload.official_text'),
        );
        $this->assertSame([50, 50, 50, 50, 50, 50, 50, 51, 51, 51, 51, 51], $objectives->pluck('physical_page')->values()->all());
        $this->assertFalse($objectives->contains(fn (array $candidate): bool => str_starts_with((string) data_get($candidate, 'structured_payload.official_text'), 'Objetivo de aprendizaje')));

        $skills = collect($parsed['candidates'])->where('entity_type', 'skill_objective')->keyBy('candidate_key');
        $this->assertSame(
            'Explorar y observar la naturaleza, usando los sentidos apropiadamente durante investigaciones experimentales guiadas.',
            data_get($skills, 'objective:OAH:a.structured_payload.official_text'),
        );
        $this->assertSame(
            'Comunicar y comparar con otros sus ideas, observaciones y experiencias de forma oral y escrita, y por medio de juegos de roles y dibujos, entre otros.',
            data_get($skills, 'objective:OAH:d.structured_payload.official_text'),
        );
    }
}
