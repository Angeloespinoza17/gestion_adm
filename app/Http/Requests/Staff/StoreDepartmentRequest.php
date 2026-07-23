<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\Staff\Concerns\NormalizesNullableFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    use NormalizesNullableFields;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeNullableFields(['description', 'responsible_staff_id', 'color']);
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
