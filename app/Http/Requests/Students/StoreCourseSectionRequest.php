<?php

namespace App\Http\Requests\Students;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'section_name' => strtoupper(trim((string) $this->input('section_name'))),
            'capacity' => $this->input('capacity') !== null && $this->input('capacity') !== ''
                ? (int) $this->input('capacity')
                : null,
            'active' => $this->has('active')
                ? filter_var($this->input('active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : true,
        ]);
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'education_level_id' => ['required', 'integer', 'exists:education_levels,id'],
            'section_name' => [
                'required',
                'string',
                'max:20',
                Rule::unique('course_sections')->where(function ($query) {
                    return $query
                        ->where('academic_year_id', $this->input('academic_year_id'))
                        ->where('education_level_id', $this->input('education_level_id'))
                        ->where('section_name', $this->input('section_name'));
                }),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
