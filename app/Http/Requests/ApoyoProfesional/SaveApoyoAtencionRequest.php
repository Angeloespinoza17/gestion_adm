<?php

namespace App\Http\Requests\ApoyoProfesional;

use App\Models\ApoyoProfesional\ApoyoAtencion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveApoyoAtencionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'teacher_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'apoyo_profesional_id' => ['nullable', 'integer', 'exists:apoyo_profesionales,id'],
            'attended_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'attention_type_id' => ['nullable', 'integer', 'exists:apoyo_config_tipos_atencion,id'],
            'motive_id' => ['nullable', 'integer', 'exists:apoyo_config_motivos,id'],
            'attended_at' => ['required', 'date'],
            'attention_type_label' => ['nullable', 'string', 'max:120'],
            'attention_type_other' => ['nullable', 'string', 'max:160'],
            'modality' => ['required', Rule::in(array_column(ApoyoAtencion::MODALITY_OPTIONS, 'value'))],
            'modality_other' => ['nullable', 'string', 'max:160'],
            'origin' => ['required', Rule::in(array_column(ApoyoAtencion::ORIGIN_OPTIONS, 'value'))],
            'origin_other' => ['nullable', 'string', 'max:160'],
            'priority_level' => ['required', Rule::in(array_column(ApoyoAtencion::PRIORITY_OPTIONS, 'value'))],
            'confidentiality_level' => ['required', Rule::in(array_column(ApoyoAtencion::CONFIDENTIALITY_OPTIONS, 'value'))],
            'reason_summary' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'professional_observations' => ['nullable', 'string'],
            'agreements' => ['nullable', 'string'],
            'recommendations' => ['nullable', 'string'],
            'next_action' => ['nullable', 'string'],
            'status' => ['required', Rule::in(array_column(ApoyoAtencion::STATUS_OPTIONS, 'value'))],
            'case_closed_notes' => ['nullable', 'string'],
        ];
    }
}
