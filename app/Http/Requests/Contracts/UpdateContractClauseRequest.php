<?php

namespace App\Http\Requests\Contracts;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContractClauseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'clause_type' => ['nullable', 'string', 'max:100'],
            'content' => ['sometimes', 'string'],
            'active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_required' => ['sometimes', 'boolean'],
            'observations' => ['nullable', 'string'],
        ];
    }
}
