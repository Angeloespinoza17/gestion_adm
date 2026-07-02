<?php

namespace App\Http\Requests\Porter;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePorterKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'maintenance_dependency_id' => ['nullable', 'integer', 'exists:maintenance_dependencies,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'code' => ['required', 'string', 'max:80', Rule::unique('porter_keys', 'code')],
            'name' => ['required', 'string', 'max:191'],
            'observations' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
