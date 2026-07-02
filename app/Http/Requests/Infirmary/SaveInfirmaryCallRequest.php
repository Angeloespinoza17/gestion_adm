<?php

namespace App\Http\Requests\Infirmary;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInfirmaryCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'attention_id' => ['nullable', 'integer', 'exists:infirmary_attentions,id'],
            'called_at' => ['required', 'date'],
            'person_contacted' => ['required', 'string', 'max:160'],
            'relationship' => ['nullable', 'string', 'max:120'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'call_status' => ['required', Rule::in(['pendiente', 'contesto', 'no_contesto', 'mensaje_dejado'])],
            'reason' => ['nullable', 'string', 'max:191'],
            'conversation_summary' => ['nullable', 'string'],
            'commitments' => ['nullable', 'string'],
            'estimated_arrival_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'called_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
