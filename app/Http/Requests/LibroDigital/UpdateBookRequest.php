<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.books.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['sometimes', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['sometimes', 'integer', 'exists:course_sections,id'],
            'schedule_subject_id' => ['sometimes', 'integer', 'exists:schedule_subjects,id'],
            'teacher_staff_id' => ['sometimes', 'integer', 'exists:staff,id'],
            'name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'normative_profile_id' => ['sometimes', 'nullable', 'integer', 'exists:lcd_regulatory_profiles,id'],
            'regulatory_profile_id' => ['sometimes', 'nullable', 'integer', 'exists:lcd_regulatory_profiles,id'],
            'modality' => ['sometimes', 'nullable', 'string', 'max:60'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
