<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOperationalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'book_id' => ['sometimes', 'nullable', 'integer', 'exists:lcd_books,id'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['sometimes', 'nullable', 'integer', 'exists:course_sections,id'],
            'occurred_on' => ['required', 'date_format:Y-m-d'],
            'title' => ['required', 'string', 'max:191'],
            'category' => ['nullable', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:5000'],
            'actions' => ['nullable', 'string', 'max:3000'],
            'status' => ['sometimes', Rule::in(['open'])],
            'confidentiality_level' => ['sometimes', Rule::in(['general', 'reserved', 'restricted', 'high_confidentiality'])],
            'professional_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
        ];
    }
}
