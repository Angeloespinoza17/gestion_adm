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

        if (is_string($this->input('staff_ids'))) {
            $decoded = json_decode($this->input('staff_ids'), true);
            $this->merge(['staff_ids' => is_array($decoded) ? $decoded : []]);
        }
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
            'staff_ids' => ['sometimes', 'array'],
            'staff_ids.*' => ['integer', 'distinct', 'exists:staff,id'],
        ];
    }
}
