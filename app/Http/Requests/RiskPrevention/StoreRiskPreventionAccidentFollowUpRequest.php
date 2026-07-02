<?php

namespace App\Http\Requests\RiskPrevention;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRiskPreventionAccidentFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'followed_at' => ['required', 'date'],
            'status' => ['required', Rule::in(['abierto', 'en_seguimiento', 'cerrado'])],
            'notes' => ['required', 'string'],
            'next_actions' => ['nullable', 'string'],
        ];
    }
}
