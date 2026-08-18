<?php

namespace App\Http\Requests\Psychology;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignPsychologyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['user_id' => ['required', 'integer', 'exists:users,id'], 'reason' => ['required', 'string', 'max:2000'], 'expected_first_review_at' => ['nullable', 'date', 'after_or_equal:now'], 'professional_priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])]];
    }
}
