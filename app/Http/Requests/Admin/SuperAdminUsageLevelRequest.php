<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SuperAdminUsageLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'group' => ['nullable', Rule::in(['staff', 'student'])],
            'period' => ['nullable', Rule::in(['30', '90', '365', 'all'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'account_status' => ['nullable', Rule::in(['active', 'inactive'])],
            'usage_status' => ['nullable', Rule::in(['with_usage', 'without_usage'])],
            'sort' => ['nullable', Rule::in(['name', 'logins', 'usage', 'active_days', 'last_activity'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
