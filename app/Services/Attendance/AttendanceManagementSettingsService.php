<?php

namespace App\Services\Attendance;

use App\Models\Attendance\AttendanceManagementSetting;

class AttendanceManagementSettingsService
{
    public function forYear(int $academicYearId): array
    {
        $defaults = [
            'risk_thresholds' => config('attendance_management.risk_thresholds', []),
            'risk_weights' => config('attendance_management.risk_weights', []),
            'pattern_settings' => config('attendance_management.pattern_settings', []),
            'alert_settings' => config('attendance_management.alert_settings', []),
            'recovery_settings' => config('attendance_management.recovery_settings', []),
        ];
        $setting = AttendanceManagementSetting::query()
            ->where('academic_year_id', $academicYearId)
            ->orWhereNull('academic_year_id')
            ->orderByRaw('CASE WHEN academic_year_id IS NULL THEN 1 ELSE 0 END')
            ->first();
        if (! $setting) {
            return $defaults;
        }

        foreach (array_keys($defaults) as $key) {
            $defaults[$key] = array_replace($defaults[$key], (array) $setting->{$key});
        }

        return $defaults;
    }
}
