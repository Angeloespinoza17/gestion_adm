<?php

namespace App\Http\Requests\Convivencia;

use App\Models\Convivencia\ConvivenciaDerivation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveConvivenciaDerivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'case_id' => ['nullable', 'integer', 'exists:convivencia_cases,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'destination_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'destination_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'destination_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'external_institution_id' => ['nullable', 'integer', 'exists:convivencia_external_institutions,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'scope' => ['required', Rule::in(array_column(ConvivenciaDerivation::SCOPE_OPTIONS, 'value'))],
            'status' => ['required', Rule::in(array_column(ConvivenciaDerivation::STATUS_OPTIONS, 'value'))],
            'priority_level' => ['required', Rule::in(array_column(ConvivenciaDerivation::PRIORITY_OPTIONS, 'value'))],
            'confidentiality_level' => ['required', 'string', 'max:50'],
            'destination_label' => ['nullable', 'string', 'max:191'],
            'external_contact_name' => ['nullable', 'string', 'max:160'],
            'external_contact_email' => ['nullable', 'email', 'max:191'],
            'external_contact_phone' => ['nullable', 'string', 'max:80'],
            'derived_at' => ['required', 'date'],
            'sent_at' => ['nullable', 'date'],
            'response_due_at' => ['nullable', 'date'],
            'responded_at' => ['nullable', 'date'],
            'closed_at' => ['nullable', 'date'],
            'motive' => ['required', 'string'],
            'narrative' => ['nullable', 'string'],
            'response_text' => ['nullable', 'string'],
            'suggested_actions' => ['nullable', 'string'],
            'follow_up_notes' => ['nullable', 'string'],
            'is_sensitive' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $scope = $this->input('scope');

            if ($scope === 'internal' && !$this->filled('destination_department_id') && !$this->filled('destination_staff_id') && !$this->filled('destination_user_id') && !$this->filled('destination_label')) {
                $validator->errors()->add('destination_label', 'Debes indicar un destinatario interno para la derivación.');
            }

            if ($scope === 'external' && !$this->filled('external_institution_id') && !$this->filled('destination_label')) {
                $validator->errors()->add('external_institution_id', 'Debes indicar una institución externa para la derivación.');
            }
        });
    }
}
