<?php

namespace App\Services\PedagogicalManagement\ClassPresentations;

use App\Exceptions\PedagogicalManagement\ClassPresentationGenerationException;
use App\Models\PedagogicalManagement\ClassPresentation;
use ZipArchive;

class PresentationArtifactQualityService
{
    /** @param list<string> $previews */
    public function validate(ClassPresentation $presentation, string $pptx, ?string $pdf, array $previews): void
    {
        if (! is_file($pptx) || filesize($pptx) <= 0) {
            throw new ClassPresentationGenerationException('El PowerPoint generado está vacío.', 'PPTX_EMPTY');
        }
        $zip = new ZipArchive;
        if ($zip->open($pptx) !== true) {
            throw new ClassPresentationGenerationException('El PowerPoint no es un paquete ZIP válido.', 'PPTX_ZIP_INVALID');
        }
        try {
            $slideCount = 0;
            $slideXml = '';
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = (string) $zip->getNameIndex($index);
                if (preg_match('#^ppt/slides/slide\d+\.xml$#', $name)) {
                    $slideCount++;
                    $content = $zip->getFromIndex($index);
                    if (is_string($content)) {
                        $slideXml .= ' '.$content;
                    }
                }
            }
            $expected = (int) data_get($presentation->configuration, 'slide_count');
            if ($slideCount !== $expected) {
                throw new ClassPresentationGenerationException('El PowerPoint no contiene exactamente las diapositivas solicitadas.', 'PPTX_SLIDE_COUNT_INVALID');
            }
            foreach (collect(data_get($presentation->curricular_snapshot, 'objectives', []))->pluck('code')->filter() as $code) {
                if (! str_contains(mb_strtolower($slideXml), mb_strtolower(htmlspecialchars((string) $code, ENT_XML1)))) {
                    throw new ClassPresentationGenerationException('El PowerPoint no contiene un objetivo curricular seleccionado.', 'PPTX_OBJECTIVE_MISSING');
                }
            }
        } finally {
            $zip->close();
        }

        if ((bool) data_get($presentation->configuration, 'generate_pdf') && (! $pdf || ! is_file($pdf) || filesize($pdf) <= 0)) {
            throw new ClassPresentationGenerationException('El PDF adicional solicitado no fue generado.', 'PDF_MISSING');
        }
        if (count($previews) !== (int) data_get($presentation->configuration, 'slide_count')) {
            throw new ClassPresentationGenerationException('No fue posible crear la previsualización completa.', 'PREVIEW_COUNT_INVALID');
        }
        foreach ($previews as $preview) {
            if (! is_file($preview) || filesize($preview) <= 0) {
                throw new ClassPresentationGenerationException('Una previsualización quedó vacía.', 'PREVIEW_EMPTY');
            }
        }
    }
}
