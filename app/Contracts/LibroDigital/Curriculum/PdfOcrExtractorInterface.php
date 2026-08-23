<?php

namespace App\Contracts\LibroDigital\Curriculum;

interface PdfOcrExtractorInterface
{
    public function available(): bool;

    /** @return array{text:string,confidence:float,warnings:list<string>} */
    public function extractPage(string $absolutePath, int $physicalPage): array;
}
