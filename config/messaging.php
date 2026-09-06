<?php

return [
    // Las instalaciones de producción deben activar el módulo de forma explícita
    // después de validar colas, Reverb y el proxy TLS. Local/testing se mantienen
    // habilitados para que la degradación HTTP y la suite funcionen sin .env extra.
    'enabled' => (bool) env(
        'MESSAGING_ENABLED',
        env('APP_ENV', 'production') !== 'production'
    ),
    'realtime' => [
        // En producción se habilita sólo después de confirmar Reverb, proxy TLS
        // y un worker que consuma la cola `broadcasts`.
        'enabled' => (bool) env(
            'MESSAGING_REALTIME_ENABLED',
            env('APP_ENV', 'production') !== 'production'
        ),
        'connection_grace_ms' => (int) env('MESSAGING_REALTIME_GRACE_MS', 10000),
    ],
    'polling' => [
        // Respaldo degradado: no debe competir con una conexión realtime sana.
        'enabled' => (bool) env('MESSAGING_HTTP_FALLBACK_ENABLED', true),
        'interval_ms' => (int) env('MESSAGING_POLL_INTERVAL_MS', 60000),
        'active_interval_ms' => (int) env('MESSAGING_ACTIVE_POLL_INTERVAL_MS', 30000),
        'max_interval_ms' => (int) env('MESSAGING_MAX_POLL_INTERVAL_MS', 120000),
        'reconciliation_interval_ms' => (int) env('MESSAGING_RECONCILIATION_INTERVAL_MS', 300000),
        'jitter_ratio' => (float) env('MESSAGING_POLL_JITTER_RATIO', 0.20),
        'recovery_limit' => (int) env('MESSAGING_RECOVERY_LIMIT', 100),
    ],
    'messages' => [
        'edit_window_minutes' => (int) env('MESSAGING_EDIT_WINDOW_MINUTES', 15),
        'max_length' => (int) env('MESSAGING_MAX_MESSAGE_LENGTH', 20000),
    ],
    'attachments' => [
        'max_files' => (int) env('MESSAGING_MAX_ATTACHMENTS_PER_MESSAGE', 10),
        'max_size_mb' => (int) env('MESSAGING_MAX_ATTACHMENT_MB', 20),
        'disk' => env('MESSAGING_FILESYSTEM_DISK', 'local'),
        'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'jpg', 'jpeg', 'png', 'webp'],
        'mimes' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'text/plain', 'text/csv', 'image/jpeg', 'image/png', 'image/webp'],
        'temporary_minutes' => 60,
    ],
    'acknowledgements' => [
        'manual_reminder_cooldown_minutes' => (int) env('MESSAGING_MANUAL_REMINDER_COOLDOWN_MINUTES', 30),
        'allow_after_due_date' => (bool) env('MESSAGING_ALLOW_ACK_AFTER_DUE_DATE', true),
        'automatic_reminder_hours' => [24, 2],
    ],
    'announcements' => [
        'chunk_size' => (int) env('MESSAGING_ANNOUNCEMENT_CHUNK_SIZE', 500),
        'confirmation_threshold' => (int) env('MESSAGING_ANNOUNCEMENT_CONFIRMATION_THRESHOLD', 20),
    ],
    'reactions' => ['👍', '❤️', '✅', '🎉', '👀', '🙏'],
];
