<?php

namespace App\Http\Requests\Convivencia;

use App\Models\Convivencia\ConvivenciaCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveConvivenciaCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'case_type_item_id' => ['nullable', 'integer', 'exists:convivencia_catalog_items,id'],
            'classification_item_id' => ['required', 'integer', 'exists:convivencia_catalog_items,id'],
            'subclassification_item_id' => ['nullable', 'integer', 'exists:convivencia_catalog_items,id'],
            'criticality_item_id' => ['required', 'integer', 'exists:convivencia_catalog_items,id'],
            'responsible_user_id' => ['required', 'integer', 'exists:users,id'],
            'responsible_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'opened_at' => ['required', 'date'],
            'happened_at' => ['nullable', 'date'],
            'origin' => ['required', Rule::in(array_column(ConvivenciaCase::ORIGIN_OPTIONS, 'value'))],
            'status' => ['nullable', Rule::in(array_column(ConvivenciaCase::STATUS_OPTIONS, 'value'))],
            'case_type_label' => ['nullable', 'string', 'max:160'],
            'classification_label' => ['nullable', 'string', 'max:160'],
            'subclassification_label' => ['nullable', 'string', 'max:160'],
            'criticality_label' => ['nullable', 'string', 'max:100'],
            'place' => ['nullable', 'string', 'max:160'],
            'initial_report' => ['required', 'string', 'min:10'],
            'background' => ['nullable', 'string'],
            'immediate_measures' => ['nullable', 'string'],
            'safeguarding_measures' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'resolution' => ['nullable', 'string'],
            'conclusion' => ['nullable', 'string'],
            'follow_up_due_at' => ['nullable', 'date'],
            'is_sensitive' => ['sometimes', 'boolean'],
            'people' => ['nullable', 'array'],
            'people.*.student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'people.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'people.*.staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'people.*.course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'people.*.person_type' => ['required_with:people', Rule::in(array_column(ConvivenciaCase::PERSON_TYPE_OPTIONS, 'value'))],
            'people.*.role_type' => ['required_with:people', Rule::in(array_column(ConvivenciaCase::PERSON_ROLE_OPTIONS, 'value'))],
            'people.*.full_name' => ['required_with:people', 'string', 'max:191'],
            'people.*.identifier' => ['nullable', 'string', 'max:80'],
            'people.*.relationship_label' => ['nullable', 'string', 'max:120'],
            'people.*.contact_reference' => ['nullable', 'string', 'max:191'],
            'people.*.notes' => ['nullable', 'string'],
            'people.*.is_sensitive' => ['sometimes', 'boolean'],
        ];
    }
}
