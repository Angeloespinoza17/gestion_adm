<?php

return [
    'timezone' => env('ATTENDANCE_MANAGEMENT_TIMEZONE', 'America/Santiago'),
    'cache_ttl_seconds' => 600,
    'analysis_chunk_size' => 200,
    'risk_thresholds' => [
        'green' => 95,
        'yellow' => 90,
        'orange' => 85,
        'critical_absence_streak' => 5,
        'critical_drop_points' => 8,
        'critical_recent_absences' => 5,
        'critical_recent_school_days' => 10,
    ],
    'risk_weights' => [
        'accumulated_attendance' => 35,
        'last_30_days' => 20,
        'last_15_days' => 15,
        'absence_streak' => 15,
        'unjustified_absences' => 8,
        'late_arrivals' => 4,
        'previous_case' => 3,
    ],
    'pattern_settings' => [
        'minimum_weekday_observations' => 5,
        'weekday_rate_multiplier' => 1.75,
        'minimum_pattern_occurrences' => 3,
        'recent_window_school_days' => 20,
        'comparison_window_school_days' => 20,
        'meaningful_change_points' => 5,
    ],
    'alert_settings' => [
        'cooldown_days' => 7,
        'days_without_intervention' => 5,
        'days_without_family_contact' => 7,
        'auto_open_cases' => false,
    ],
    'recovery_settings' => [
        'minimum_last_30_rate' => 90,
        'minimum_improvement_points' => 5,
        'maximum_absence_streak' => 2,
    ],
];
