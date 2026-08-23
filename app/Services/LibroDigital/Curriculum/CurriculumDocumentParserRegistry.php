<?php

namespace App\Services\LibroDigital\Curriculum;

use App\Contracts\LibroDigital\Curriculum\CurriculumDocumentParserInterface;

class CurriculumDocumentParserRegistry
{
    /** @param iterable<CurriculumDocumentParserInterface> $parsers */
    public function __construct(private readonly iterable $parsers) {}

    public function for(string $documentType): CurriculumDocumentParserInterface
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($documentType)) {
                return $parser;
            }
        }

        return new GenericCurriculumDocumentParser(new PdfTextNormalizer);
    }
}
