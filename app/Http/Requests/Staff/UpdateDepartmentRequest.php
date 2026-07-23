<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\Staff\Concerns\NormalizesNullableFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
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
        $departmentId = $this->route('department')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:191', Rule::unique('departments', 'name')->ignore($departmentId)],
            'description' => ['nullable', 'string'],
            'responsible_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'active' => ['sometimes', 'boolean'],
            'color' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
