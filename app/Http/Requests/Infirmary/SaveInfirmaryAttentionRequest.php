<?php

namespace App\Http\Requests\Infirmary;

use App\Models\Infirmary\InfirmaryAttentionReferral;
use App\Models\Infirmary\InfirmaryAttentionTreatment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInfirmaryAttentionRequest extends FormRequest
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
            'referred_by_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'dependency_id' => ['nullable', 'integer', 'exists:maintenance_dependencies,id'],
            'attended_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'attention_category' => ['required', 'string', 'max:120'],
            'attended_at' => ['required', 'date'],
            'accompanied_by_type' => ['required', Rule::in(['sin_acompanante', 'inspectora', 'profesor', 'apoderado', 'otro'])],
            'accompanied_by_name' => ['nullable', 'string', 'max:160'],
            'consultation_reason' => ['required', 'string'],
            'initial_description' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'attention_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'priority' => ['required', Rule::in(['baja', 'media', 'alta', 'emergencia'])],
            'status' => ['required', Rule::in(['abierta', 'en_atencion', 'finalizada'])],

            'treatments' => ['sometimes', 'array'],
            'treatments.*.treatment_types' => ['nullable', 'array'],
            'treatments.*.treatment_types.*' => ['string', Rule::in(InfirmaryAttentionTreatment::TYPE_OPTIONS)],
            'treatments.*.treatment_other' => ['nullable', 'string', 'max:160'],
            'treatments.*.medication_id' => ['nullable', 'integer', 'exists:infirmary_medications,id'],
            'treatments.*.medication_quantity' => ['nullable', 'numeric', 'min:0.01'],
            'treatments.*.blood_pressure' => ['nullable', 'string', 'max:40'],
            'treatments.*.pulse' => ['nullable', 'integer', 'min:1', 'max:300'],
            'treatments.*.respiratory_rate' => ['nullable', 'integer', 'min:1', 'max:120'],
            'treatments.*.temperature' => ['nullable', 'numeric', 'min:20', 'max:45'],
            'treatments.*.oxygen_saturation' => ['nullable', 'integer', 'min:1', 'max:100'],
            'treatments.*.weight' => ['nullable', 'numeric', 'min:1', 'max:300'],
            'treatments.*.height' => ['nullable', 'numeric', 'min:0.3', 'max:250'],
            'treatments.*.vital_signs_notes' => ['nullable', 'string'],
            'treatments.*.emotional_support_required' => ['nullable', 'boolean'],
            'treatments.*.emotional_comment' => ['nullable', 'string'],
            'treatments.*.emotional_support_type' => ['nullable', 'string', 'max:160'],
            'treatments.*.emotional_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'treatments.*.emotional_professional_id' => ['nullable', 'integer', 'exists:staff,id'],
            'treatments.*.other_treatments' => ['nullable', 'string'],
            'treatments.*.notes' => ['nullable', 'string'],

            'referrals' => ['sometimes', 'array'],
            'referrals.*.referral_type' => ['required', 'string', Rule::in(InfirmaryAttentionReferral::TYPE_OPTIONS)],
            'referrals.*.referred_at' => ['required', 'date'],
            'referrals.*.responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'referrals.*.responsible_name' => ['nullable', 'string', 'max:160'],
            'referrals.*.reason' => ['nullable', 'string'],
            'referrals.*.observations' => ['nullable', 'string'],
            'referrals.*.result' => ['nullable', 'string'],

            'calls' => ['sometimes', 'array'],
            'calls.*.called_at' => ['required', 'date'],
            'calls.*.person_contacted' => ['required', 'string', 'max:160'],
            'calls.*.relationship' => ['nullable', 'string', 'max:120'],
            'calls.*.phone_number' => ['nullable', 'string', 'max:50'],
            'calls.*.call_status' => ['required', Rule::in(['pendiente', 'contesto', 'no_contesto', 'mensaje_dejado'])],
            'calls.*.reason' => ['nullable', 'string', 'max:191'],
            'calls.*.conversation_summary' => ['nullable', 'string'],
            'calls.*.commitments' => ['nullable', 'string'],
            'calls.*.estimated_arrival_at' => ['nullable', 'date'],
            'calls.*.duration_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'calls.*.called_by_user_id' => ['nullable', 'integer', 'exists:users,id'],

            'follow_ups' => ['sometimes', 'array'],
            'follow_ups.*.followed_at' => ['required', 'date'],
            'follow_ups.*.responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'follow_ups.*.comment' => ['required', 'string'],
            'follow_ups.*.status' => ['required', Rule::in(['pendiente', 'en_proceso', 'cerrado'])],
            'follow_ups.*.next_review_at' => ['nullable', 'date'],
        ];
    }
}
