<?php

namespace App\Http\Requests\Psychology;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePsychologyReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'suggested_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'origin_area' => ['nullable', 'string', 'max:100'],
            'suggested_urgency' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'primary_reason' => ['required', 'string', 'max:160'],
            'secondary_reasons' => ['nullable', 'string', 'max:2000'],
            'observed_facts' => ['required', 'string', 'min:10', 'max:12000'],
            'approximate_started_on' => ['nullable', 'date', 'before_or_equal:today'],
            'people_involved' => ['nullable', 'string', 'max:4000'], 'measures_taken' => ['nullable', 'string', 'max:6000'],
            'known_previous_interventions' => ['nullable', 'string', 'max:6000'],
            'observed_risk_indicators' => ['nullable', 'string', 'max:4000'],
            'immediate_response_needed' => ['required', 'boolean'], 'guardian_informed' => ['required', 'boolean'],
            'guardian_contact_status' => ['required', Rule::in(['not_contacted', 'pending', 'contacted', 'unreachable', 'not_applicable'])],
            'observations' => ['nullable', 'string', 'max:6000'], 'purpose_declaration_accepted' => ['required', 'boolean'],
            'submit' => ['sometimes', 'boolean'],
        ];
    }
}
