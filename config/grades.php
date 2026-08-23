<?php

return [
    'imports_disk' => env('GRADE_IMPORTS_DISK', env('ATTENDANCE_IMPORTS_DISK', 'local')),
    'imports_path' => env('GRADE_IMPORTS_PATH', 'grades/imports'),
    'max_upload_kb' => (int) env('GRADE_MAX_UPLOAD_KB', 25600),
];
