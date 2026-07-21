<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAttendanceGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'name' => ['required', 'string', 'max:160'],
            'scope_type' => ['required', Rule::in(['institution', 'cycle', 'level', 'course', 'student'])],
            'scope_id' => ['nullable', 'integer', 'required_unless:scope_type,institution,student'],
            'student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id', 'required_if:scope_type,student'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'target_rate' => ['required', 'numeric', 'between:0,100'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'completed', 'cancelled'])],
            'justification' => ['nullable', 'string', 'max:2000'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
