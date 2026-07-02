<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191', 'unique:departments,name'],
            'description' => ['nullable', 'string'],
            'responsible_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'active' => ['sometimes', 'boolean'],
            'color' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
