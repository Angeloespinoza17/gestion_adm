<?php

namespace App\Http\Requests\Students;

use App\Models\StudentEnrollment;
use App\Support\DateInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enrolled_at' => DateInput::normalize($this->input('enrolled_at')) ?: now()->format('Y-m-d'),
            'withdrawn_at' => DateInput::normalize($this->input('withdrawn_at')),
            'enrollment_status' => $this->input('enrollment_status') ?: 'matriculada',
        ]);
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['required', 'integer', 'exists:course_sections,id'],
            'enrollment_status' => ['required', Rule::in(array_column(StudentEnrollment::STATUS_OPTIONS, 'value'))],
            'enrolled_at' => ['nullable', 'date'],
            'withdrawn_at' => ['nullable', 'date', 'after_or_equal:enrolled_at'],
            'observations' => ['nullable', 'string'],
        ];
    }
}
