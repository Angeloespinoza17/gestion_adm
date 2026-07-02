<?php

namespace App\Http\Requests\Students;

use App\Models\StudentEnrollment;
use App\Support\DateInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['enrolled_at', 'withdrawn_at'] as $field) {
            if ($this->exists($field)) {
                $data[$field] = DateInput::normalize($this->input($field));
            }
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        return [
            'course_section_id' => ['sometimes', 'integer', 'exists:course_sections,id'],
            'enrollment_status' => ['sometimes', Rule::in(array_column(StudentEnrollment::STATUS_OPTIONS, 'value'))],
            'enrolled_at' => ['nullable', 'date'],
            'withdrawn_at' => ['nullable', 'date', 'after_or_equal:enrolled_at'],
            'observations' => ['nullable', 'string'],
        ];
    }
}
