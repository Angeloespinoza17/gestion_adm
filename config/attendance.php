<?php

return [
    'imports_disk' => env('ATTENDANCE_IMPORTS_DISK', 'local'),
    'imports_path' => env('ATTENDANCE_IMPORTS_PATH', 'attendance/imports'),
    'max_upload_kb' => (int) env('ATTENDANCE_MAX_UPLOAD_KB', 25600),
    'target_threshold' => (float) env('ATTENDANCE_TARGET_THRESHOLD', 85),
    'warning_threshold' => (float) env('ATTENDANCE_WARNING_THRESHOLD', 90),
    'critical_threshold' => (float) env('ATTENDANCE_CRITICAL_THRESHOLD', 85),
    'consecutive_absence_threshold' => (int) env('ATTENDANCE_CONSECUTIVE_ABSENCE_THRESHOLD', 3),
    'projection' => [
        'monthly_unit_value' => (float) env('ATTENDANCE_MONTHLY_UNIT_VALUE', 0),
        'attendance_factor' => (float) env('ATTENDANCE_FACTOR', 1),
        'target_attendance_rate' => (float) env('ATTENDANCE_TARGET_RATE', 85),
        'conservative_delta' => (float) env('ATTENDANCE_CONSERVATIVE_DELTA', 5),
        'custom_attendance_rate' => (float) env('ATTENDANCE_CUSTOM_RATE', 90),
        'additional_adjustments' => (float) env('ATTENDANCE_ADJUSTMENTS', 0),
        'annual_school_days' => (int) env('ATTENDANCE_ANNUAL_SCHOOL_DAYS', 190),
        'calculation_window' => env('ATTENDANCE_CALCULATION_WINDOW', 'current_month'),
        'currency' => env('ATTENDANCE_CURRENCY', 'CLP'),
    ],
];
