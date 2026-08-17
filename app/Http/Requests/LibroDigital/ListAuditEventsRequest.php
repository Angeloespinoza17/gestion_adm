<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;

class ListAuditEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.audit.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['sometimes', 'nullable', 'integer', 'exists:lcd_schools,id'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:academic_years,id'],
            'entity_type' => ['sometimes', 'nullable', 'string', 'max:120'],
            'event_type' => ['sometimes', 'nullable', 'string', 'max:120'],
            'action' => ['sometimes', 'nullable', 'string', 'max:80'],
            'actor_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'correlation_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'query' => ['sometimes', 'nullable', 'string', 'max:120'],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
