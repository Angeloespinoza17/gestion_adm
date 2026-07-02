<?php

namespace App\Http\Requests\ApoyoProfesional;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApoyoReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::in(['diario', 'semanal', 'mensual', 'semestral', 'anual'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'professional_id' => ['nullable', 'integer', 'exists:users,id'],
            'professional_role_name' => ['nullable', 'string', 'max:120'],
            'attention_type_label' => ['nullable', 'string', 'max:120'],
            'motive_label' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'string', 'max:40'],
            'professional_area_name' => ['nullable', 'string', 'max:120'],
            'confidentiality_level' => ['nullable', Rule::in(['general', 'reservada', 'confidencial', 'alta_confidencialidad'])],
            'anonymize' => ['nullable', 'boolean'],
        ];
    }
}
