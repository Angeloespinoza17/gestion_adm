<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.books.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:lcd_schools,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['required', 'integer', 'exists:course_sections,id'],
            'schedule_subject_id' => ['required', 'integer', 'exists:schedule_subjects,id'],
            'teacher_staff_id' => ['required', 'integer', 'exists:staff,id'],
            'name' => ['nullable', 'string', 'max:191'],
            'normative_profile_id' => ['nullable', 'integer', 'exists:lcd_regulatory_profiles,id'],
            'regulatory_profile_id' => ['nullable', 'integer', 'exists:lcd_regulatory_profiles,id'],
            'modality' => ['nullable', Rule::in(['regular', 'special', 'adult', 'parvularia'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
