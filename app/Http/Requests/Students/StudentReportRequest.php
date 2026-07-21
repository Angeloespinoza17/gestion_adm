<?php

namespace App\Http\Requests\Students;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'period' => ['nullable', Rule::in(['academic_year', 'semester_1', 'semester_2', 'month', 'custom'])],
            'month' => ['nullable', 'date_format:Y-m'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'general_status' => ['nullable', 'string', 'max:50'],
            'enrollment_status' => ['nullable', 'string', 'max:50'],
            'is_pie_participant' => ['nullable', 'boolean'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'commune' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'refresh' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $nullableKeys = [
            'academic_year_id',
            'month',
            'from',
            'to',
            'education_level_id',
            'course_section_id',
            'general_status',
            'enrollment_status',
            'is_pie_participant',
            'nationality',
            'commune',
            'search',
            'refresh',
        ];

        $normalized = [];

        foreach ($nullableKeys as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $normalized[$key] = null;
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }
}
