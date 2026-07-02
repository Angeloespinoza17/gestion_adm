<?php

namespace App\Http\Requests\Staff\Permissions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $permissionTypeId = $this->route('permissionType')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:191', Rule::unique('permission_types', 'name')->ignore($permissionTypeId)],
            'description' => ['nullable', 'string'],
            'requires_attachment' => ['sometimes', 'boolean'],
            'allows_with_pay' => ['sometimes', 'boolean'],
            'allows_without_pay' => ['sometimes', 'boolean'],
            'allows_hourly' => ['sometimes', 'boolean'],
            'allows_half_day' => ['sometimes', 'boolean'],
            'requires_manager_approval' => ['sometimes', 'boolean'],
            'requires_direction_approval' => ['sometimes', 'boolean'],
            'requires_hr_approval' => ['sometimes', 'boolean'],
            'max_days' => ['nullable', 'numeric', 'min:0'],
            'minimum_notice_days' => ['nullable', 'integer', 'min:0'],
            'allows_retroactive' => ['sometimes', 'boolean'],
            'affects_salary' => ['sometimes', 'boolean'],
            'affects_attendance' => ['sometimes', 'boolean'],
            'requires_replacement' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
