<?php

namespace App\Http\Requests\Staff;

use App\Models\StaffPermissionWatcher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SyncStaffPermissionWatchersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'watchers' => ['nullable', 'array'],
            'watchers.*.target_type' => ['required', Rule::in(array_column(StaffPermissionWatcher::TARGET_OPTIONS, 'value'))],
            'watchers.*.role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'watchers.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'watchers.*.notify' => ['sometimes', 'boolean'],
            'watchers.*.can_view' => ['sometimes', 'boolean'],
            'watchers.*.active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('watchers', []) as $index => $watcher) {
                $targetType = $watcher['target_type'] ?? null;

                if ($targetType === 'role' && empty($watcher['role_id'])) {
                    $validator->errors()->add("watchers.$index.role_id", 'Debes seleccionar un rol.');
                }

                if ($targetType === 'user' && empty($watcher['user_id'])) {
                    $validator->errors()->add("watchers.$index.user_id", 'Debes seleccionar un usuario.');
                }
            }
        });
    }
}
