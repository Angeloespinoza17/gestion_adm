<?php

namespace App\Http\Requests\Spaces;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDependencyTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $typeId = $this->route('dependencyType')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:191', Rule::unique('dependency_types', 'name')->ignore($typeId)],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
