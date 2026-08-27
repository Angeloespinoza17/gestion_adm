<?php

return [
    'enabled' => env('MESSAGING_ENABLED', true),
    'polling' => [
        'enabled' => true,
        'interval_ms' => (int) env('MESSAGING_POLL_INTERVAL_MS', 15000),
        'active_interval_ms' => (int) env('MESSAGING_ACTIVE_POLL_INTERVAL_MS', 8000),
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
