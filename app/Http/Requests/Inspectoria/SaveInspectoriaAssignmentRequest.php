<?php

namespace App\Http\Requests\Inspectoria;

use Illuminate\Foundation\Http\FormRequest;

class SaveInspectoriaAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['required', 'integer', 'exists:course_sections,id'],
            'inspector_staff_id' => ['required', 'integer', 'exists:staff,id'],
            'physical_location' => ['nullable', 'string', 'max:191'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
