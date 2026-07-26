<?php

namespace App\Http\Requests\RiskPrevention;

use Illuminate\Foundation\Http\FormRequest;

class BulkStoreRiskPreventionEppItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.name' => ['required', 'string', 'max:160'],
            'items.*.epp_type' => ['required', 'string', 'max:120'],
            'items.*.stock' => ['required', 'integer', 'min:0'],
            'items.*.minimum_stock' => ['required', 'integer', 'min:0'],
            'items.*.unit' => ['required', 'string', 'max:50'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'El archivo no contiene elementos EPP válidos.',
            'items.max' => 'Puedes cargar hasta 500 elementos por archivo.',
        ];
    }
}
