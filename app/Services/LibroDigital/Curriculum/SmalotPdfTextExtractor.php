<?php

namespace App\Services\LibroDigital\Curriculum;

use App\Contracts\LibroDigital\Curriculum\PdfTextExtractorInterface;
use App\Exceptions\LibroDigital\LibroDigitalException;
use Smalot\PdfParser\Parser;
use Throwable;

class SmalotPdfTextExtractor implements PdfTextExtractorInterface
{
    public function __construct(private readonly PdfTextNormalizer $normalizer) {}

    public function extract(string $absolutePath): array
    {
        try {
            $document = (new Parser)->parseFile($absolutePath);
            $details = $document->getDetails();
            $pages = [];
            foreach ($document->getPages() as $index => $page) {
                $raw = trim((string) $page->getText());
                $normalized = $this->normalizer->normalize($raw);
                $length = mb_strlen($normalized);
                $warnings = [];
                if ($length < 40) {
                    $warnings[] = 'text_layer_insufficient';
                }
                $pages[] = [
                    'physical_page_number' => $index + 1,
                    'printed_page_label' => $this->normalizer->printedPageLabel($raw),
                    'raw_text' => $raw,
                    'normalized_text' => $normalized,
                    'layout_text' => $raw,
                    'ocr_text' => null,
                    'extraction_method' => $length >= 40 ? 'text_layer' : 'text_layer_insufficient',
                    'confidence' => $length >= 120 ? 0.98 : ($length >= 40 ? 0.82 : 0.20),
                    'has_tables' => preg_match('/(?:INDICADORES|HORAS|OBJETIVOS).{0,80}(?:UNIDAD|EVALUACI)/isu', $normalized) === 1,
                    'has_images' => count($page->getXObjects()) > 0,
                    'processing_warnings' => $warnings,
                    'content_hash' => hash('sha256', $normalized),
                ];
            }

            return ['details' => $details, 'pages' => $pages];
        } catch (Throwable $exception) {
            throw new LibroDigitalException(
                'El PDF está cifrado, corrupto o no puede ser interpretado por el extractor instalado.',
                'LCD_CURRICULUM_PDF_PARSE_FAILED',
                422,
                [['message' => $exception->getMessage()]],
            );
        }
    }
}
