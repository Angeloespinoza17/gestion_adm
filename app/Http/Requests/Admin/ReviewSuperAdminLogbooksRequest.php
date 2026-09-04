<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewSuperAdminLogbooksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', Rule::in(['inspectoria', 'porter', 'infirmary', 'convivencia'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'category' => ['nullable', 'string', 'max:160'],
            'priority' => ['nullable', Rule::in(['baja', 'media', 'alta', 'urgente'])],
            'status' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
