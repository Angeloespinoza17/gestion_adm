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

        $updateRules = $this->isMethod('POST') ? ['nullable'] : ['required'];

        return [
            'change_reason' => [...$updateRules, 'string', 'min:5', 'max:1000'],
            'record_updated_at' => [...$updateRules, 'date'],
            'type' => ['required', 'string', 'max:80'],
            'interview_number' => ['nullable', 'integer', 'between:1,9999'],
            'activity_on' => ['required', 'date'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i', 'after_or_equal:starts_at'],
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
            'follow_up_type' => ['nullable', 'required_with:next_action_on', Rule::in(['phone_call', 'new_interview', 'guardian_contact', 'student_check_in', 'teacher_coordination', 'external_coordination', 'case_review', 'other'])],
            'next_steps' => ['nullable', 'string', 'max:6000'],
            'next_action_on' => ['nullable', 'required_with:follow_up_type,next_steps', 'date', 'after_or_equal:activity_on'],
            'acknowledgement_status' => ['nullable', Rule::in(['not_requested', 'pending', 'acknowledged', 'declined'])],
            'acknowledged_name' => ['required_if:acknowledgement_status,acknowledged', 'nullable', 'string', 'max:191'],
            'acknowledged_rut' => ['nullable', 'string', 'max:30'],
            'acknowledged_at' => ['nullable', 'date'],
            'acknowledgement_observations' => ['nullable', 'string', 'max:2000'],
            'attendance_status' => ['nullable', Rule::in(['scheduled', 'completed', 'absent', 'cancelled', 'rescheduled', 'realizada'])],
            'visibility' => ['required', Rule::in(['private_psychology', 'psychology_team', 'interdisciplinary_team', 'referral_feedback'])],
            'referral_feedback' => ['nullable', 'string', 'max:6000'],
            'status' => ['sometimes', Rule::in(['draft', 'finalized'])],
            'coordination' => ['nullable', 'array'],
            'coordination.recipient_user_id' => ['required_with:coordination', 'integer', 'exists:users,id'],
            'coordination.coordination_type' => ['required_with:coordination', Rule::in(['meeting', 'information_request', 'case_review', 'classroom_support', 'family_support', 'protocol_coordination', 'other'])],
            'coordination.subject' => ['required_with:coordination', 'string', 'max:191'],
            'coordination.request_message' => ['required_with:coordination', 'string', 'max:4000'],
            'coordination.requested_for' => ['nullable', 'date', 'after_or_equal:activity_on'],
        ];
    }

    public function messages(): array
    {
        return [
            'participant_types.required' => 'Selecciona al menos una persona participante.',
            'participant_types.min' => 'Selecciona al menos una persona participante.',
            'change_reason.required' => 'Indica brevemente por qué se corrige la ficha.',
            'change_reason.min' => 'El motivo de la corrección debe tener al menos 5 caracteres.',
            'record_updated_at.required' => 'Recarga la ficha antes de editarla para comprobar su versión actual.',
            'interviewee_type.required' => 'Selecciona el tipo de persona entrevistada.',
            'interviewee_name.required' => 'Indica el nombre de la persona entrevistada.',
            'ends_at.after_or_equal' => 'La hora de término no puede ser anterior a la hora de inicio.',
            'follow_up_type.required_with' => 'Selecciona el tipo de seguimiento.',
            'next_action_on.required_with' => 'Indica la fecha del seguimiento.',
            'next_action_on.after_or_equal' => 'La fecha de seguimiento no puede ser anterior a la atención.',
            'coordination.recipient_user_id.required_with' => 'Selecciona el funcionario destinatario.',
            'coordination.coordination_type.required_with' => 'Selecciona el tipo de coordinación.',
            'coordination.subject.required_with' => 'Indica el asunto de la coordinación.',
            'coordination.request_message.required_with' => 'Describe el requerimiento de coordinación.',
        ];
    }
}
