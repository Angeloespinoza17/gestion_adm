<?php

namespace App\Http\Requests\Students;

use App\Models\StudentPromotion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromoteStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'from_course_section_id' => ['required', 'integer', 'exists:course_sections,id'],
            'to_academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'to_course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'students' => ['required', 'array', 'min:1'],
            'students.*.student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'students.*.promotion_status' => ['required', Rule::in(array_column(StudentPromotion::STATUS_OPTIONS, 'value'))],
            'students.*.to_course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'students.*.notes' => ['nullable', 'string'],
        ];
    }
}
