<?php

namespace App\Http\Requests\Contracts;

use Illuminate\Foundation\Http\FormRequest;

class PreviewContractTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $clauseIds = $this->input('clause_ids');

        if (is_string($clauseIds)) {
            $decoded = json_decode($clauseIds, true);
            $this->merge([
                'clause_ids' => is_array($decoded) ? $decoded : [],
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string'],
            'clause_ids' => ['sometimes', 'array'],
            'clause_ids.*' => ['integer', 'exists:contract_clauses,id'],
        ];
    }
}
