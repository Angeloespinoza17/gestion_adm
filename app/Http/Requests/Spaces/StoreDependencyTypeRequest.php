<?php

namespace App\Http\Requests\Spaces;

use Illuminate\Foundation\Http\FormRequest;

class StoreDependencyTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191', 'unique:dependency_types,name'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
