<?php

namespace App\Http\Requests\Contracts;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractClauseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'clause_type' => ['nullable', 'string', 'max:100'],
            'content' => ['required', 'string'],
            'active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_required' => ['sometimes', 'boolean'],
            'observations' => ['nullable', 'string'],
        ];
    }
}
