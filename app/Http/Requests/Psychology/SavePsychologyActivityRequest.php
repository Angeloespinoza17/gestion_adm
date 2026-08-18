<?php

namespace App\Http\Requests\Psychology;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePsychologyActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isInterview = fn () => in_array($this->input('type'), ['student_interview', 'guardian_interview', 'teacher_interview'], true);

        return [
            'type' => ['required', 'string', 'max:80'],
            'interview_number' => ['nullable', 'integer', 'between:1,9999'],
            'activity_on' => ['required', 'date'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i', 'after:starts_at'],
            'modality' => ['nullable', 'string', 'max:40'],
            'location' => ['nullable', 'string', 'max:160'],
            'participants' => ['nullable', 'string', 'max:4000'],
            'participant_types' => [Rule::requiredIf($isInterview), 'nullable', 'array', 'min:1', 'max:5'],
            'participant_types.*' => ['string', Rule::in(['student', 'guardian', 'teacher', 'education_assistant', 'other'])],
            'interviewee_type' => [Rule::requiredIf($isInterview), 'nullable', Rule::in(['student', 'guardian', 'teacher', 'education_assistant', 'other'])],
            'interviewee_name' => [Rule::requiredIf($isInterview), 'nullable', 'string', 'max:191'],
            'interviewee_rut' => ['nullable', 'string', 'max:30'],
            'interviewer_position_snapshot' => ['nullable', 'string', 'max:160'],
            'objective' => ['nullable', 'string', 'max:4000'],
            'institutional_summary' => ['nullable', 'string', 'max:10000'],
            'private_note' => ['nullable', 'string', 'max:20000'],
            'general_background' => ['nullable', 'string', 'max:20000'],
            'result' => ['nullable', 'string', 'max:6000'],
            'agreements' => ['nullable', 'string', 'max:6000'],
            'next_steps' => ['nullable', 'string', 'max:6000'],
            'next_action_on' => ['nullable', 'date'],
            'next_interview_at' => ['nullable', 'date'],
            'acknowledgement_status' => ['nullable', Rule::in(['not_requested', 'pending', 'acknowledged', 'declined'])],
            'acknowledged_name' => ['required_if:acknowledgement_status,acknowledged', 'nullable', 'string', 'max:191'],
            'acknowledged_rut' => ['nullable', 'string', 'max:30'],
            'acknowledged_at' => ['nullable', 'date'],
            'acknowledgement_observations' => ['nullable', 'string', 'max:2000'],
            'attendance_status' => ['nullable', Rule::in(['scheduled', 'completed', 'absent', 'cancelled', 'rescheduled', 'realizada'])],
            'visibility' => ['required', Rule::in(['private_psychology', 'psychology_team', 'interdisciplinary_team', 'referral_feedback'])],
            'referral_feedback' => ['nullable', 'string', 'max:6000'],
            'status' => ['sometimes', Rule::in(['draft', 'finalized'])],
        ];
    }
}
