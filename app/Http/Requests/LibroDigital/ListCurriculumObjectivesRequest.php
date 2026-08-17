<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCurriculumObjectivesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.lesson.manage')
            || $this->user()?->hasPermission('libro_digital.books.view')
            || false;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:lcd_schools,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'book_id' => ['sometimes', 'nullable', 'string', 'max:40', 'regex:/^(?:[0-9]+|[0-9A-HJKMNP-TV-Z]{26})$/i'],
            'schedule_subject_id' => ['sometimes', 'nullable', 'integer', 'exists:schedule_subjects,id'],
            'catalog_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'course_section_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'grade_code' => ['sometimes', 'nullable', 'string', 'max:60'],
            'level_code' => ['sometimes', 'nullable', 'string', 'max:60'],
            'curriculum_track' => ['sometimes', 'nullable', 'string', 'max:30'],
            'subject_code' => ['sometimes', 'nullable', 'string', 'max:100'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:160'],
            'objective_type' => ['sometimes', 'nullable', 'string', 'max:20'],
            'axis_code' => ['sometimes', 'nullable', 'string', 'max:80'],
            'status' => ['sometimes', 'nullable', Rule::in(['all', 'active', 'inactive'])],
            'source' => ['sometimes', 'nullable', 'string', 'max:180'],
            'query' => ['sometimes', 'nullable', 'string', 'max:180'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
