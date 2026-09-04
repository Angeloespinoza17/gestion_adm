<?php

namespace App\Http\Requests\Psychology;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePsychologyCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                'open',
                'assessment',
                'active_intervention',
                'monitoring',
                'awaiting_information',
                'awaiting_external_response',
                'paused',
                'externally_referred',
                'closure_pending',
                'reopened',
            ])],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'confidentiality' => ['required', Rule::in(['private_psychology', 'psychology_team', 'interdisciplinary_team'])],
            'general_reason' => ['required', 'string', 'max:191'],
            'categories' => ['nullable', 'string', 'max:3000'],
            'objectives' => ['nullable', 'string', 'max:6000'],
            'next_action' => ['nullable', 'string', 'max:2000'],
            'next_review_on' => ['nullable', 'date'],
            'guardian_information_status' => ['required', Rule::in([
                'pending',
                'informed',
                'not_required',
                'unable_to_contact',
                'institutional_exception',
            ])],
            'change_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'general_reason.required' => 'Indica el motivo general del caso.',
            'change_reason.required' => 'Indica brevemente por qué se actualiza el expediente.',
            'change_reason.min' => 'El motivo de la actualización debe tener al menos 5 caracteres.',
        ];
    }
}
