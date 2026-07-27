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
            'requirement_type_id' => ['nullable', 'integer', 'exists:prevent_staff_requirement_types,id'],
            'training_type' => ['required', Rule::in(['induccion', 'actualizacion', 'obligatoria'])],
            'training_date' => ['required', 'date'],
            'modality' => ['required', 'string', 'max:120'],
            'is_requirement' => ['sometimes', 'boolean'],
            'observations' => ['nullable', 'string'],
            'evidence' => ['nullable', 'file', 'max:10240'],
            'participants' => ['nullable', 'array'],
            'participants.*.id' => ['nullable', 'integer', 'exists:prevent_training_participants,id'],
            'participants.*.staff_id' => ['nullable', 'integer', 'distinct', 'exists:staff,id'],
            'participants.*.employee_name' => ['nullable', 'required_without:participants.*.staff_id', 'string', 'max:160'],
            'participants.*.compliance_status' => ['required_with:participants', Rule::in(['cumplido', 'pendiente', 'no_asiste'])],
            'participants.*.issued_on' => ['nullable', 'date'],
            'participants.*.expires_on' => ['nullable', 'date'],
            'participants.*.notes' => ['nullable', 'string'],
        ];
    }
}
