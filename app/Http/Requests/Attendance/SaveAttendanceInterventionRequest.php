<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAttendanceInterventionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'attendance_alert_id' => ['nullable', 'integer', 'exists:attendance_alerts,id'],
            'convivencia_case_id' => ['nullable', 'integer', 'exists:convivencia_cases,id'],
            'risk_level_id' => ['nullable', 'integer', 'exists:attendance_risk_levels,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', Rule::in(['new', 'pending_review', 'family_contact', 'intervention', 'follow_up', 'improved', 'no_improvement', 'referred', 'closed'])],
            'probable_cause' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:5000'],
            'opened_at' => ['nullable', 'date'],
            'first_contact_at' => ['nullable', 'date'],
            'first_action_at' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date'],
            'result' => ['nullable', 'string', 'max:80'],
            'closure_reason' => ['nullable', 'string', 'max:2000', 'required_if:status,closed'],
            'reason' => ['required', 'string', 'max:500'],
            'actions' => ['nullable', 'array', 'max:30'],
            'actions.*.action_type' => ['required_with:actions', 'string', 'max:80'],
            'actions.*.title' => ['required_with:actions', 'string', 'max:160'],
            'actions.*.description' => ['nullable', 'string', 'max:2000'],
            'actions.*.scheduled_at' => ['nullable', 'date'],
            'actions.*.responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
