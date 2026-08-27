<?php

namespace App\Services\Remuneration\Payslips;

use RuntimeException;
use Smalot\PdfParser\Parser;

class PayslipPdfTextExtractor
{
    public function __construct(private readonly PayslipOcrService $ocrService) {}

    /**
     * @return array<int,array{text:string,tokens:array<int,array{x:float,y:float,text:string}>,page_number:int,extraction_method:string,confidence:float}>
     */
    public function extract(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('No se encontró el PDF privado que se debe procesar.');
        }

        $document = (new Parser)->parseFile($path);
        $result = [];
        foreach ($document->getPages() as $index => $page) {
            $text = trim($page->getText());
            $tokens = collect($page->getDataTm())
                ->map(fn (array $entry): array => [
                    'x' => (float) ($entry[0][4] ?? 0),
                    'y' => (float) ($entry[0][5] ?? 0),
                    'text' => trim((string) ($entry[1] ?? '')),
                ])
                ->filter(fn (array $token): bool => $token['text'] !== '')
                ->values()
                ->all();

            $method = 'native';
            $confidence = 1.0;
            if (mb_strlen($text) < 80 || count($tokens) < 10) {
                $ocr = $this->ocrService->extract($path, $index + 1);
                if ($ocr) {
                    $text = $ocr['text'];
                    $tokens = $ocr['tokens'];
                    $method = 'ocr';
                    $confidence = $ocr['confidence'];
                }
            }

            $result[] = [
                'text' => $text,
                'tokens' => $tokens,
                'page_number' => $index + 1,
                'extraction_method' => $method,
                'confidence' => $confidence,
            ];
        }

        return $result;
    }
}
