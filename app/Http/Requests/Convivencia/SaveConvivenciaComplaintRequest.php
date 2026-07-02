<?php

namespace App\Http\Requests\Convivencia;

use App\Models\Convivencia\ConvivenciaComplaint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveConvivenciaComplaintRequest extends FormRequest
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
            'affected_student_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'situation_type_item_id' => ['nullable', 'integer', 'exists:convivencia_catalog_items,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'case_id' => ['nullable', 'integer', 'exists:convivencia_cases,id'],
            'complainant_name' => ['nullable', 'string', 'max:191'],
            'complainant_type' => ['required', Rule::in(array_column(ConvivenciaComplaint::COMPLAINANT_TYPE_OPTIONS, 'value'))],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'contact_phone' => ['nullable', 'string', 'max:80'],
            'situation_type_label' => ['nullable', 'string', 'max:160'],
            'place' => ['nullable', 'string', 'max:160'],
            'received_at' => ['nullable', 'date'],
            'happened_at' => ['nullable', 'date'],
            'report_text' => ['required', 'string', 'min:10'],
            'involved_snapshot' => ['nullable', 'array'],
            'involved_snapshot.*.person_type' => ['nullable', 'string', 'max:60'],
            'involved_snapshot.*.role_type' => ['nullable', 'string', 'max:60'],
            'involved_snapshot.*.full_name' => ['required_with:involved_snapshot', 'string', 'max:191'],
            'involved_snapshot.*.identifier' => ['nullable', 'string', 'max:80'],
            'involved_snapshot.*.contact_reference' => ['nullable', 'string', 'max:191'],
            'truth_declaration_accepted' => ['sometimes', 'boolean'],
            'is_anonymous' => ['sometimes', 'boolean'],
            'is_sensitive' => ['sometimes', 'boolean'],
            'status' => ['required', Rule::in(array_column(ConvivenciaComplaint::STATUS_OPTIONS, 'value'))],
            'admissibility_result' => ['nullable', 'string'],
        ];
    }
}
