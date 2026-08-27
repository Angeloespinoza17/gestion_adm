<?php

namespace App\Services\Remuneration\Payslips;

use Symfony\Component\Process\Process;

class PayslipOcrService
{
    /** @return array{text:string,tokens:array<int,array{x:float,y:float,text:string}>,confidence:float}|null */
    public function extract(string $pdfPath, int $pageNumber): ?array
    {
        $config = config('remuneration_payslips.ocr', []);
        $renderer = (string) ($config['pdftoppm_binary'] ?? '');
        $tesseract = (string) ($config['tesseract_binary'] ?? '');
        if (! ($config['enabled'] ?? false) || ! is_executable($renderer) || ! is_executable($tesseract)) {
            return null;
        }

        $temporaryBase = sys_get_temp_dir().'/rem_payslip_'.bin2hex(random_bytes(8));
        $imagePath = $temporaryBase.'.png';

        try {
            $render = new Process([$renderer, '-f', (string) $pageNumber, '-l', (string) $pageNumber, '-singlefile', '-png', $pdfPath, $temporaryBase]);
            $render->setTimeout(120);
            $render->mustRun();

            $ocr = new Process([$tesseract, $imagePath, 'stdout', '-l', (string) ($config['language'] ?? 'spa')]);
            $ocr->setTimeout(120);
            $ocr->mustRun();
            $text = trim($ocr->getOutput());

            return $text === '' ? null : [
                'text' => $text,
                'tokens' => [],
                'confidence' => (float) ($config['confidence'] ?? 0.55),
            ];
        } finally {
            if (is_file($imagePath)) {
                unlink($imagePath);
            }
        }
    }
}
