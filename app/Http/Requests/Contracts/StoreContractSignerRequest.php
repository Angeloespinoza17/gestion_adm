<?php

namespace App\Http\Requests\Contracts;

use App\Models\ContractSigner;
use App\Support\Rut;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractSignerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'rut' => Rut::normalize($this->input('rut')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'rut' => ['nullable', 'string', 'max:20'],
            'position' => ['nullable', 'string', 'max:255'],
            'signer_type' => ['required', Rule::in(array_column(ContractSigner::TYPE_OPTIONS, 'value'))],
            'signature_image' => ['nullable', 'file', 'image', 'max:5120'],
            'active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'observations' => ['nullable', 'string'],
        ];
    }
}
