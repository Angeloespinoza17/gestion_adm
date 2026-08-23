<?php

namespace App\Http\Requests\Grades;

use Illuminate\Foundation\Http\FormRequest;

class GradeStudentStatisticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['sometimes', 'nullable', 'integer', 'exists:lcd_schools,id'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['sometimes', 'nullable', 'integer', 'exists:course_sections,id'],
            'schedule_subject_id' => ['sometimes', 'nullable', 'integer', 'exists:schedule_subjects,id'],
            'assessment_period_code' => ['sometimes', 'nullable', 'string', 'max:80', 'exists:lcd_assessment_periods,code'],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:10', 'max:100'],
        ];
    }
}
