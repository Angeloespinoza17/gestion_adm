<?php

namespace App\Http\Requests\Students;

use App\Support\DateInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->exists('year')) {
            $data['year'] = $this->input('year') !== null && $this->input('year') !== ''
                ? (int) $this->input('year')
                : null;
        }

        foreach (['starts_at', 'ends_at'] as $field) {
            if ($this->exists($field)) {
                $data[$field] = DateInput::normalize($this->input($field));
            }
        }

        foreach (['is_active', 'is_closed'] as $field) {
            if ($this->exists($field)) {
                $data[$field] = filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        $academicYearId = $this->route('academicYear')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:191'],
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2100', Rule::unique('academic_years', 'year')->ignore($academicYearId)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
            'is_closed' => ['sometimes', 'boolean'],
        ];
    }
}
