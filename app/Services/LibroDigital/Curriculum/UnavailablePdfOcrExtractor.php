<?php

namespace App\Services\LibroDigital\Curriculum;

use App\Contracts\LibroDigital\Curriculum\PdfOcrExtractorInterface;

class UnavailablePdfOcrExtractor implements PdfOcrExtractorInterface
{
    public function available(): bool
    {
        return false;
    }

    public function extractPage(string $absolutePath, int $physicalPage): array
    {
        return [
            'text' => '',
            'confidence' => 0,
            'warnings' => ['ocr_runtime_unavailable'],
        ];
    }
}
