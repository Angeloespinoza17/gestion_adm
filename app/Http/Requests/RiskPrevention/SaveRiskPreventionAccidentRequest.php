<?php

namespace App\Http\Requests\RiskPrevention;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRiskPreventionAccidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'occurred_at' => ['required', 'date'],
            'accident_type' => ['required', Rule::in(['student', 'staff', 'visit'])],
            'involved_person_name' => ['required', 'string', 'max:160'],
            'involved_person_identifier' => ['nullable', 'string', 'max:120'],
            'location' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string'],
            'injuries' => ['nullable', 'string'],
            'measures_taken' => ['nullable', 'string'],
            'referrals' => ['nullable', 'string'],
            'case_status' => ['required', Rule::in(['abierto', 'en_seguimiento', 'cerrado'])],
            'responsible_name' => ['nullable', 'string', 'max:160'],
        ];
    }
}
