<?php

namespace App\Http\Requests\Inspectoria;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInspectoriaBulkAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'course_section_ids' => ['required', 'array', 'min:1', 'max:500'],
            'course_section_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('course_sections', 'id')->where('active', true),
            ],
            'inspector_staff_id' => ['required', 'integer', Rule::exists('staff', 'id')->where('active', true)],
            'physical_location' => ['nullable', 'string', 'max:191'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'course_section_ids.required' => 'Selecciona al menos un curso.',
            'course_section_ids.min' => 'Selecciona al menos un curso.',
            'course_section_ids.*.exists' => 'Uno de los cursos seleccionados no está disponible.',
            'inspector_staff_id.required' => 'Selecciona la inspectora responsable.',
        ];
    }
}
