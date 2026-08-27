<?php

namespace App\Http\Requests\PedagogicalManagement;

use Illuminate\Foundation\Http\FormRequest;

class SyncPedagogicalCoordinatorAssignmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('pedagogical-coordinators.configure') === true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:lcd_schools,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'coordinator_user_id' => ['required', 'integer', 'exists:users,id'],
            'course_ids' => ['sometimes', 'array', 'max:100'],
            'course_ids.*' => ['integer', 'distinct', 'exists:course_sections,id'],
            'education_level_ids' => ['sometimes', 'array', 'max:30'],
            'education_level_ids.*' => ['integer', 'distinct', 'exists:education_levels,id'],
        ];
    }
}
