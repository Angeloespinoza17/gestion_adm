<?php

return [
    'company' => [
        'key' => env('RISK_MATRIX_COMPANY_KEY', 'institution'),
        'name' => env('RISK_MATRIX_COMPANY_NAME', env('APP_NAME', 'Institución')),
        'tax_id' => env('RISK_MATRIX_COMPANY_TAX_ID'),
        'address' => env('RISK_MATRIX_COMPANY_ADDRESS'),
        'economic_activity_code' => env('RISK_MATRIX_ECONOMIC_ACTIVITY_CODE'),
    ],
    'methodology_code' => env('RISK_MATRIX_METHODOLOGY', 'ISP-2025-VEP'),
    'program_due_days' => (int) env('RISK_MATRIX_PROGRAM_DUE_DAYS', 30),
    'annual_review_months' => (int) env('RISK_MATRIX_REVIEW_MONTHS', 12),
    'import' => [
        'max_kilobytes' => (int) env('RISK_MATRIX_IMPORT_MAX_KB', 20480),
        'preview_rows' => (int) env('RISK_MATRIX_IMPORT_PREVIEW_ROWS', 50),
        'max_rows' => (int) env('RISK_MATRIX_IMPORT_MAX_ROWS', 10000),
    ],
    'evidence' => [
        'max_kilobytes' => (int) env('RISK_MATRIX_EVIDENCE_MAX_KB', 20480),
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'xlsx', 'xls', 'doc', 'docx'],
    ],
];
