<?php

return [
    'disk' => env('BACKUP_DISK', 'local'),
    'path' => trim(env('BACKUP_PATH', 'backups/database'), '/'),
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 35),
    'schedule' => env('BACKUP_SCHEDULE', '0 3 * * 0'),
    'timeout' => (int) env('BACKUP_TIMEOUT', 3600),
];
