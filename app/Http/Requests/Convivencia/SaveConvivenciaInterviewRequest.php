<?php

namespace App\Http\Requests\Convivencia;

use App\Models\Convivencia\ConvivenciaInterview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveConvivenciaInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'case_id' => ['nullable', 'integer', 'exists:convivencia_cases,id'],
            'student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'interview_type_item_id' => ['nullable', 'integer', 'exists:convivencia_catalog_items,id'],
            'responsible_user_id' => ['required', 'integer', 'exists:users,id'],
            'responsible_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'interview_type_label' => ['required_without:interview_type_item_id', 'string', 'max:160'],
            'interview_at' => ['required', 'date'],
            'motive' => ['required', 'string'],
            'topics' => ['nullable', 'string'],
            'agreements' => ['nullable', 'string'],
            'commitments' => ['nullable', 'string'],
            'follow_up_date' => ['nullable', 'date'],
            'follow_up_status' => ['required', Rule::in(array_column(ConvivenciaInterview::FOLLOW_UP_STATUS_OPTIONS, 'value'))],
            'internal_notes' => ['nullable', 'string'],
            'is_sensitive' => ['sometimes', 'boolean'],
            'participants' => ['required', 'array', 'min:1'],
            'participants.*.student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'participants.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'participants.*.staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'participants.*.participant_type' => ['required_with:participants', 'string', 'max:60'],
            'participants.*.participant_role' => ['nullable', 'string', 'max:80'],
            'participants.*.full_name' => ['required_with:participants', 'string', 'max:191'],
            'participants.*.contact_reference' => ['nullable', 'string', 'max:191'],
            'participants.*.notes' => ['nullable', 'string'],
        ];
    }
}
