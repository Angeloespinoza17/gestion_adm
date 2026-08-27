<?php

namespace App\Http\Requests\PedagogicalManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PedagogicalStatisticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('pedagogical-instruments.statistics');
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:lcd_schools,id'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:academic_years,id'],
            'owner_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'subject_id' => ['sometimes', 'nullable', 'integer', 'exists:schedule_subjects,id'],
            'course_id' => ['sometimes', 'nullable', 'integer', 'exists:course_sections,id'],
            'education_level_id' => ['sometimes', 'nullable', 'integer', 'exists:education_levels,id'],
            'decision' => ['sometimes', 'nullable', Rule::in(['approved', 'approved_with_observations', 'rectification_requested'])],
            'prompt_version' => ['sometimes', 'nullable', 'string', 'max:50'],
            'from_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ];
    }
}
