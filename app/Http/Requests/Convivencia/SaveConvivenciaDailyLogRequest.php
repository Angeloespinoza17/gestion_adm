<?php

namespace App\Http\Requests\Convivencia;

use App\Models\Convivencia\ConvivenciaDailyLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveConvivenciaDailyLogRequest extends FormRequest
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
            'daily_log_type_item_id' => ['nullable', 'integer', 'exists:convivencia_catalog_items,id'],
            'inspector_user_id' => ['required', 'integer', 'exists:users,id'],
            'inspector_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'happened_at' => ['required', 'date'],
            'daily_log_type_label' => ['required_without:daily_log_type_item_id', 'string', 'max:160'],
            'place' => ['nullable', 'string', 'max:160'],
            'description' => ['required', 'string'],
            'immediate_action' => ['nullable', 'string'],
            'involved_snapshot' => ['nullable', 'array'],
            'involved_snapshot.*.full_name' => ['required_with:involved_snapshot', 'string', 'max:191'],
            'guardian_informed' => ['sometimes', 'boolean'],
            'guardian_contact_note' => ['nullable', 'string'],
            'status' => ['required', Rule::in(array_column(ConvivenciaDailyLog::STATUS_OPTIONS, 'value'))],
            'is_sensitive' => ['sometimes', 'boolean'],
        ];
    }
}
