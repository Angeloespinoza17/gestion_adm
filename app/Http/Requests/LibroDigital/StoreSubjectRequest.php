<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.subject_catalog.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:lcd_schools,id'],
            'name' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:50', 'unique:schedule_subjects,code'],
            'area' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'active' => ['nullable', 'boolean'],
            'display_name' => ['nullable', 'string', 'max:191'],
            'subject_type' => ['nullable', Rule::in(['official', 'common_plan', 'differentiated', 'workshop', 'institutional'])],
            'description' => ['nullable', 'string', 'max:1000'],
            'education_types' => ['nullable', 'array', 'max:3'],
            'education_types.*' => ['string', 'distinct', Rule::in(['parvularia', 'basica', 'media'])],
            'official_code' => ['prohibited'],
            'type' => ['prohibited'],
        ];
    }
}
