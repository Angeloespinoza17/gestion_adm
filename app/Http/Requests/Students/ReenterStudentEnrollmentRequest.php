<?php

namespace App\Http\Requests\Students;

use App\Support\DateInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReenterStudentEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'effective_date' => DateInput::normalize($this->input('effective_date')) ?: now()->format('Y-m-d'),
            'enrollment_status' => $this->input('enrollment_status') ?: 'regular',
        ]);
    }

    public function rules(): array
    {
        return [
            'course_section_id' => ['required', 'integer', 'exists:course_sections,id'],
            'effective_date' => ['nullable', 'date'],
            'enrollment_status' => ['required', Rule::in(['matriculada', 'regular', 'suspendida'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
