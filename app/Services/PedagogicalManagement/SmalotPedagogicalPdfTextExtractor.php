<?php

namespace App\Services\PedagogicalManagement;

use App\Contracts\LibroDigital\Curriculum\PdfTextExtractorInterface as CurriculumPdfTextExtractor;
use App\Contracts\PedagogicalManagement\PdfTextExtractorInterface;

class SmalotPedagogicalPdfTextExtractor implements PdfTextExtractorInterface
{
    public function __construct(
        private readonly CurriculumPdfTextExtractor $extractor,
        private readonly InstrumentTextNormalizer $normalizer,
    ) {}

    public function extract(string $absolutePath): array
    {
        $result = $this->extractor->extract($absolutePath);
        $pages = collect($result['pages'] ?? [])->map(fn (array $page): array => [
            'page_number' => (int) ($page['physical_page_number'] ?? 0),
            'raw_text' => (string) ($page['raw_text'] ?? ''),
            'normalized_text' => $this->normalizer->normalize((string) ($page['raw_text'] ?? '')),
            'has_images' => (bool) ($page['has_images'] ?? false),
            'warnings' => array_values((array) ($page['processing_warnings'] ?? [])),
        ])->values()->all();

        return ['details' => (array) ($result['details'] ?? []), 'pages' => $pages];
    }

    public function name(): string
    {
        return 'smalot/pdfparser';
    }

    public function version(): string
    {
        return '2.12.5';
    }
}
