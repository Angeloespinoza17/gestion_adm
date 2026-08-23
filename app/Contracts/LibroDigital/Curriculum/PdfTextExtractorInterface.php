<?php

namespace App\Contracts\LibroDigital\Curriculum;

interface PdfTextExtractorInterface
{
    /**
     * @return array{details:array<string,mixed>,pages:list<array<string,mixed>>}
     */
    public function extract(string $absolutePath): array;
}
