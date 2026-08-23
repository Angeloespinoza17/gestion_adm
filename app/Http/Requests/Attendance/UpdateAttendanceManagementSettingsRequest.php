<?php

namespace App\Http\Requests\Attendance;

use App\Services\Attendance\AttendanceManagementAccessService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceManagementSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(AttendanceManagementAccessService::class)->hasAnyForConfiguration($this->user());
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'risk_thresholds' => ['required', 'array'],
            'risk_thresholds.green' => ['required', 'numeric', 'between:0,100'],
            'risk_thresholds.yellow' => ['required', 'numeric', 'between:0,100', 'lte:risk_thresholds.green'],
            'risk_thresholds.orange' => ['required', 'numeric', 'between:0,100', 'lte:risk_thresholds.yellow'],
            'risk_thresholds.critical_absence_streak' => ['required', 'integer', 'min:2', 'max:30'],
            'risk_thresholds.critical_drop_points' => ['required', 'numeric', 'min:1', 'max:100'],
            'risk_thresholds.critical_recent_absences' => ['required', 'integer', 'min:1', 'max:30'],
            'risk_thresholds.critical_recent_school_days' => ['required', 'integer', 'min:5', 'max:60'],
            'risk_weights' => ['required', 'array'],
            'risk_weights.*' => ['required', 'numeric', 'min:0', 'max:100'],
            'pattern_settings' => ['required', 'array'],
            'alert_settings' => ['required', 'array'],
            'recovery_settings' => ['required', 'array'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
