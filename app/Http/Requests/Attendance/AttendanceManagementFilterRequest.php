<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceManagementFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'risk_level' => ['nullable', Rule::in(['green', 'yellow', 'orange', 'red', 'critical', 'no_data'])],
            'status' => ['nullable', 'string', 'max:40'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'pattern_type' => ['nullable', 'string', 'max:80'],
            'search' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', Rule::in(['priority', 'attendance', 'trend', 'lost_days', 'student_name', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'as_of' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ];
    }
}
