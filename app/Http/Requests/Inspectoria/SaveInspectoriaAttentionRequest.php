<?php

namespace App\Http\Requests\Inspectoria;

use App\Models\Inspectoria\InspectoriaAttention;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInspectoriaAttentionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'attended_at' => ['nullable', 'date'],
            'request_types' => ['required', 'array', 'min:1'],
            'request_types.*' => ['string', Rule::in(array_keys(InspectoriaAttention::REQUEST_TYPES))],
            'actions_taken' => ['nullable', 'array'],
            'actions_taken.*' => ['string', Rule::in(array_keys(InspectoriaAttention::ACTIONS))],
            'priority' => ['required', Rule::in(['normal', 'urgente'])],
            'brief_note' => ['nullable', 'string', 'max:1000'],
            'guardian_notified' => ['boolean'],
            'requires_follow_up' => ['boolean'],
            'psychosocial_referral_user_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn () => in_array('derivacion_psicosocial', $this->input('actions_taken', []), true)),
                'exists:users,id',
            ],
        ];
    }
}
