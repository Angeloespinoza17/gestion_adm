<?php

namespace App\Services\PedagogicalManagement\ClassPresentations;

use App\Exceptions\PedagogicalManagement\ClassPresentationGenerationException;
use App\Models\PedagogicalManagement\ClassPresentation;
use Smalot\PdfParser\Parser;
use Throwable;

class TeacherGuideArtifactQualityService
{
    /** @param array<string,mixed> $deck */
    public function validate(ClassPresentation $presentation, string $pdf, array $deck): void
    {
        if (! is_file($pdf) || filesize($pdf) < 5000) {
            throw new ClassPresentationGenerationException('La guía docente en PDF está vacía o incompleta.', 'TEACHER_GUIDE_PDF_EMPTY');
        }
        $header = file_get_contents($pdf, false, null, 0, 5);
        if ($header !== '%PDF-') {
            throw new ClassPresentationGenerationException('La guía docente no es un PDF válido.', 'TEACHER_GUIDE_PDF_INVALID');
        }
        $rawPdf = file_get_contents($pdf);
        if (! is_string($rawPdf) || ! str_contains($rawPdf, '/MediaBox [0 0 595.28 841.89]')) {
            throw new ClassPresentationGenerationException('La guía docente no utiliza el formato A4 requerido.', 'TEACHER_GUIDE_PDF_PAGE_SIZE_INVALID');
        }

        try {
            $document = (new Parser)->parseFile($pdf);
            $pages = $document->getPages();
            $text = $this->normalizeText($document->getText());
        } catch (Throwable) {
            throw new ClassPresentationGenerationException('No fue posible validar el contenido de la guía docente.', 'TEACHER_GUIDE_PDF_UNREADABLE');
        }

        $expectedSlides = (int) data_get($presentation->configuration, 'slide_count');
        if (count($pages) < $expectedSlides + 2) {
            throw new ClassPresentationGenerationException('La guía docente no contiene todas las secciones esperadas.', 'TEACHER_GUIDE_PDF_PAGE_COUNT_INVALID');
        }
        if (mb_strlen($text) < 500 || ! str_contains($text, 'guía docente')) {
            throw new ClassPresentationGenerationException('La guía docente no contiene texto utilizable.', 'TEACHER_GUIDE_PDF_CONTENT_MISSING');
        }

        $guide = $deck['teacher_guide'] ?? null;
        if (! is_array($guide) || count((array) ($guide['slide_script'] ?? [])) !== $expectedSlides) {
            throw new ClassPresentationGenerationException('La guía docente almacenada no coincide con el PDF generado.', 'TEACHER_GUIDE_PDF_SOURCE_MISMATCH');
        }
    }

    private function normalizeText(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($value)));
    }
}
