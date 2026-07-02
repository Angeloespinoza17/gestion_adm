<?php

namespace App\Http\Requests\Contracts;

use App\Models\ContractSigner;
use App\Support\Rut;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContractSignerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('rut')) {
            $this->merge([
                'rut' => Rut::normalize($this->input('rut')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'rut' => ['nullable', 'string', 'max:20'],
            'position' => ['nullable', 'string', 'max:255'],
            'signer_type' => ['sometimes', Rule::in(array_column(ContractSigner::TYPE_OPTIONS, 'value'))],
            'signature_image' => ['nullable', 'file', 'image', 'max:5120'],
            'active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'observations' => ['nullable', 'string'],
        ];
    }
}
