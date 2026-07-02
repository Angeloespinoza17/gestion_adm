<?php

namespace App\Http\Requests\Infirmary;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInfirmaryAccidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attention_id' => ['nullable', 'integer', 'exists:infirmary_attentions,id'],
            'student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'dependency_id' => ['nullable', 'integer', 'exists:maintenance_dependencies,id'],
            'occurred_at' => ['required', 'date'],
            'accident_type' => ['required', 'string', 'max:120'],
            'place' => ['nullable', 'string', 'max:160'],
            'activity' => ['nullable', 'string', 'max:160'],
            'description' => ['required', 'string'],
            'witnesses' => ['nullable', 'string'],
            'present_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'severity' => ['required', Rule::in(['leve', 'moderado', 'grave', 'critico'])],
            'observed_injuries' => ['nullable', 'string'],
            'first_aid' => ['nullable', 'string'],
            'guardian_call_status' => ['nullable', Rule::in(['pendiente', 'contesto', 'no_contesto', 'mensaje_dejado'])],
            'referral_destination' => ['nullable', 'string', 'max:160'],
            'school_insurance' => ['nullable', 'boolean'],
            'diat_number' => ['nullable', 'string', 'max:80'],
            'diat_generated_at' => ['nullable', 'date'],
            'observations' => ['nullable', 'string'],
            'case_status' => ['required', Rule::in(['abierto', 'en_seguimiento', 'cerrado'])],
        ];
    }
}
