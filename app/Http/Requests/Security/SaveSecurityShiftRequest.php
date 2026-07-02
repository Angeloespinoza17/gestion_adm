<?php

namespace App\Http\Requests\Security;

use App\Models\Security\SecurityShift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSecurityShiftRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'coverage_label' => trim((string) ($this->input('coverage_label') ?: 'Todo el colegio')),
            'maintenance_dependency_id' => null,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $scheduleType = $this->input('schedule_type', SecurityShift::SCHEDULE_SINGLE);
        $isWeekly = $scheduleType === SecurityShift::SCHEDULE_WEEKLY;

        return [
            'staff_id' => ['required', 'integer', Rule::exists('staff', 'id')],
            'schedule_type' => ['required', Rule::in(array_column(SecurityShift::SCHEDULE_OPTIONS, 'value'))],
            'maintenance_dependency_id' => ['nullable'],
            'scheduled_start_at' => [$isWeekly ? 'nullable' : 'required', 'date'],
            'scheduled_end_at' => [$isWeekly ? 'nullable' : 'required', 'date', 'after:scheduled_start_at'],
            'weekdays' => [$isWeekly ? 'required' : 'nullable', 'array', 'min:1'],
            'weekdays.*' => [Rule::in(array_column(SecurityShift::WEEKDAY_OPTIONS, 'value'))],
            'template_start_time' => [$isWeekly ? 'required' : 'nullable', 'date_format:H:i'],
            'template_end_time' => [$isWeekly ? 'required' : 'nullable', 'date_format:H:i'],
            'recurrence_starts_on' => [$isWeekly ? 'required' : 'nullable', 'date'],
            'recurrence_ends_on' => ['nullable', 'date', 'after_or_equal:recurrence_starts_on'],
            'coverage_label' => ['nullable', 'string', 'max:255'],
            'general_observations' => ['nullable', 'string'],
            'closing_observations' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(array_column(SecurityShift::STATUS_OPTIONS, 'value'))],
        ];
    }
}
