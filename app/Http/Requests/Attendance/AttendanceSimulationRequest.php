<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceSimulationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'observed_present' => ['required', 'integer', 'min:0'],
            'observed_expected' => ['required', 'integer', 'min:0', 'gte:observed_present'],
            'remaining_expected' => ['required', 'integer', 'min:0'],
            'future_rate' => ['required', 'numeric', 'between:0,100'],
            'target_rate' => ['required', 'numeric', 'between:0,100'],
            'method' => ['nullable', Rule::in(['historical_average', 'moving_average', 'weighted_average', 'linear_trend', 'seasonal', 'custom_scenario'])],
            'confidence' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }
}
