<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.configuration.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['sometimes', 'nullable', 'integer', 'exists:lcd_schools,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'timezone' => ['required', 'timezone'],
            'attendance_autosave_seconds' => ['required', 'integer', 'between:5,15'],
            'feature_flags' => ['required', 'array'],
            'feature_flags.*' => ['boolean'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
