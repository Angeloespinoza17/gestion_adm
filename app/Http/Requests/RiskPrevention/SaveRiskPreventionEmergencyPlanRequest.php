<?php

namespace App\Http\Requests\RiskPrevention;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRiskPreventionEmergencyPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'record_type' => ['required', Rule::in(['plan_evacuacion', 'protocolo'])],
            'title' => ['required', 'string', 'max:180'],
            'emergency_type' => ['required', 'string', 'max:160'],
            'last_updated_at' => ['required', 'date'],
            'responsible_name' => ['required', 'string', 'max:160'],
            'notes' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
            'document' => ['nullable', 'file', 'max:10240'],
        ];
    }
}
