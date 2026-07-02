<?php

namespace App\Http\Requests\Contracts;

use App\Models\Staff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $clauseIds = $this->input('clause_ids');
        $availableVariables = $this->input('available_variables');

        $data = [];

        if (is_string($clauseIds)) {
            $decoded = json_decode($clauseIds, true);
            $data['clause_ids'] = is_array($decoded) ? $decoded : [];
        }

        if (is_string($availableVariables)) {
            $decoded = json_decode($availableVariables, true);
            $data['available_variables'] = is_array($decoded) ? $decoded : [];
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:contract_templates,name'],
            'contract_type' => ['nullable', Rule::in(array_column(Staff::CONTRACT_TYPE_OPTIONS, 'value'))],
            'description' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'body' => ['nullable', 'string'],
            'clause_ids' => ['sometimes', 'array'],
            'clause_ids.*' => ['integer', 'exists:contract_clauses,id'],
            'available_variables' => ['nullable', 'array'],
            'available_variables.*' => ['string', 'max:100'],
            'internal_notes' => ['nullable', 'string'],
        ];
    }
}
