<?php

namespace App\Http\Requests\Students;

use App\Support\DateInput;
use Illuminate\Foundation\Http\FormRequest;

class StoreAcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $year = $this->input('year');

        $this->merge([
            'year' => $year !== null && $year !== '' ? (int) $year : null,
            'name' => trim((string) ($this->input('name') ?: $this->input('year'))),
            'starts_at' => DateInput::normalize($this->input('starts_at')),
            'ends_at' => DateInput::normalize($this->input('ends_at')),
            'is_active' => $this->has('is_active')
                ? filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : false,
            'is_closed' => $this->has('is_closed')
                ? filter_var($this->input('is_closed'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : false,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100', 'unique:academic_years,year'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
            'is_closed' => ['sometimes', 'boolean'],
        ];
    }
}
