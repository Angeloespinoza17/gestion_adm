<?php

return [
    'timezone' => env('PSYCHOLOGY_TIMEZONE', config('app.timezone', 'America/Santiago')),
    'disk' => env('PSYCHOLOGY_DISK', 'local'),
    'max_file_kb' => (int) env('PSYCHOLOGY_MAX_FILE_KB', 10240),
    'allowed_mimes' => [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
    ],
    'first_review_hours' => 48,
    'first_intervention_hours' => 120,
    'inactive_days' => 14,
    'reiteration_days' => 90,
    'anonymization_threshold' => 5,
];
