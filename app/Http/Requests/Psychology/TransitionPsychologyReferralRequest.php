<?php

namespace App\Http\Requests\Psychology;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionPsychologyReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['submitted', 'under_review', 'information_requested', 'accepted', 'linked_to_existing_case', 'redirected', 'rejected', 'duplicated', 'cancelled', 'completed'])],
            'reason' => ['required_unless:status,submitted', 'nullable', 'string', 'max:2000'],
            'shared_note' => ['nullable', 'string', 'max:4000'], 'internal_note' => ['nullable', 'string', 'max:8000'],
            'professional_priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
            'information_request' => ['required_if:status,information_requested', 'nullable', 'string', 'max:4000'],
            'information_response' => ['nullable', 'string', 'max:6000'],
            'case_id' => ['nullable', 'integer', 'exists:psychology_cases,id'],
        ];
    }
}
