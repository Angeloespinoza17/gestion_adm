<?php

namespace App\Http\Requests\RiskPrevention;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRiskPreventionFireExtinguisherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $fireExtinguisherId = $this->route('fireExtinguisher')?->id;

        return [
            'code' => ['required', 'string', 'max:100', Rule::unique('prevent_fire_extinguishers', 'code')->ignore($fireExtinguisherId)],
            'extinguisher_type' => ['required', 'string', 'max:120'],
            'building' => ['required', 'string', 'max:120'],
            'floor' => ['nullable', 'string', 'max:120'],
            'dependency_name' => ['required', 'string', 'max:160'],
            'installed_at' => ['required', 'date'],
            'expires_at' => ['required', 'date', 'after:installed_at'],
            'status' => ['required', Rule::in(['vigente', 'por_vencer', 'vencido', 'dado_baja'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
