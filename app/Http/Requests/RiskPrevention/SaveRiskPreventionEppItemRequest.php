<?php

namespace App\Http\Requests\RiskPrevention;

use Illuminate\Foundation\Http\FormRequest;

class SaveRiskPreventionEppItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'epp_type' => ['required', 'string', 'max:120'],
            'stock' => ['required', 'integer', 'min:0'],
            'minimum_stock' => ['required', 'integer', 'min:0'],
            'unit' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
