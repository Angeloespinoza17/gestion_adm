<?php

namespace App\Http\Requests\Students;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->exists('section_name')) {
            $data['section_name'] = strtoupper(trim((string) $this->input('section_name')));
        }

        if ($this->exists('capacity')) {
            $data['capacity'] = $this->input('capacity') !== null && $this->input('capacity') !== ''
                ? (int) $this->input('capacity')
                : null;
        }

        if ($this->exists('active')) {
            $data['active'] = filter_var($this->input('active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        $courseSectionId = $this->route('courseSection')?->id;
        $academicYearId = $this->input('academic_year_id') ?: $this->route('courseSection')?->academic_year_id;
        $educationLevelId = $this->input('education_level_id') ?: $this->route('courseSection')?->education_level_id;
        $sectionName = $this->input('section_name') ?: $this->route('courseSection')?->section_name;

        return [
            'academic_year_id' => ['sometimes', 'integer', 'exists:academic_years,id'],
            'education_level_id' => ['sometimes', 'integer', 'exists:education_levels,id'],
            'section_name' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('course_sections')->ignore($courseSectionId)->where(function ($query) use ($academicYearId, $educationLevelId, $sectionName) {
                    return $query
                        ->where('academic_year_id', $academicYearId)
                        ->where('education_level_id', $educationLevelId)
                        ->where('section_name', $sectionName);
                }),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
