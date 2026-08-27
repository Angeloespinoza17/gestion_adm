<?php

use App\Services\Remuneration\Payslips\NumerusPayslipParser;

return [
    'disk' => env('REMUNERATION_PAYSLIP_DISK', 'local'),
    'directory' => env('REMUNERATION_PAYSLIP_DIRECTORY', 'private/remuneration/payslips'),
    'parser_version' => 'numerus-v1.0.0',
    'parsers' => [
        NumerusPayslipParser::class,
    ],
    'ocr' => [
        'enabled' => (bool) env('REMUNERATION_PAYSLIP_OCR_ENABLED', false),
        'pdftoppm_binary' => env('PDFTOPPM_BINARY', '/opt/homebrew/bin/pdftoppm'),
        'tesseract_binary' => env('TESSERACT_BINARY', '/opt/homebrew/bin/tesseract'),
        'language' => env('REMUNERATION_PAYSLIP_OCR_LANGUAGE', 'spa'),
        'confidence' => 0.55,
    ],
    'upload' => [
        'max_files' => 20,
        'max_file_kb' => 51200,
    ],
];
