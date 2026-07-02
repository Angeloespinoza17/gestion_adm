<?php

namespace App\Http\Requests\RiskPrevention;

use Illuminate\Foundation\Http\FormRequest;

class StoreRiskPreventionEmergencyDrillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'emergency_type' => ['required', 'string', 'max:160'],
            'drill_date' => ['required', 'date'],
            'responsible_name' => ['required', 'string', 'max:160'],
            'participants_count' => ['nullable', 'integer', 'min:0'],
            'findings' => ['nullable', 'string'],
            'improvements' => ['nullable', 'string'],
            'document' => ['nullable', 'file', 'max:10240'],
        ];
    }
}
