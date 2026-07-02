<?php

namespace App\Http\Requests\Infirmary;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInfirmaryMedicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'commercial_name' => ['nullable', 'string', 'max:255'],
            'active_ingredient' => ['nullable', 'string', 'max:255'],
            'presentation' => ['nullable', 'string', 'max:120'],
            'concentration' => ['nullable', 'string', 'max:120'],
            'unit' => ['nullable', 'string', 'max:40'],
            'laboratory' => ['nullable', 'string', 'max:160'],
            'current_stock' => ['nullable', 'numeric', 'min:0'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'physical_location' => ['nullable', 'string', 'max:160'],
            'batch' => ['nullable', 'string', 'max:120'],
            'manufactured_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'observations' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['disponible', 'stock_bajo', 'agotado', 'proximo_a_vencer', 'vencido'])],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
