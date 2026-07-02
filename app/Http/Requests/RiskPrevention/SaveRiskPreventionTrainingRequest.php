<?php

namespace App\Http\Requests\RiskPrevention;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRiskPreventionTrainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'training_type' => ['required', Rule::in(['induccion', 'actualizacion', 'obligatoria'])],
            'training_date' => ['required', 'date'],
            'modality' => ['required', 'string', 'max:120'],
            'observations' => ['nullable', 'string'],
            'evidence' => ['nullable', 'file', 'max:10240'],
            'participants' => ['nullable', 'array'],
            'participants.*.employee_name' => ['required_with:participants', 'string', 'max:160'],
            'participants.*.compliance_status' => ['required_with:participants', Rule::in(['cumplido', 'pendiente', 'no_asiste'])],
            'participants.*.notes' => ['nullable', 'string'],
        ];
    }
}
