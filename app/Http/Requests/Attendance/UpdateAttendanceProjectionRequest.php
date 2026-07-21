<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceProjectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'monthly_unit_value' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'attendance_factor' => ['required', 'numeric', 'min:0', 'max:100'],
            'target_attendance_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'conservative_delta' => ['required', 'numeric', 'min:0', 'max:100'],
            'custom_attendance_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'additional_adjustments' => ['required', 'numeric', 'min:-999999999999', 'max:999999999999'],
            'annual_school_days' => ['required', 'integer', 'min:1', 'max:366'],
            'calculation_window' => ['required', 'in:current_month,rolling_three_months,academic_period,custom'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'configuration_source' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
        ];
    }
}
