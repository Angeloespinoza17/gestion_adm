<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOperationalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'occurred_on' => ['sometimes', 'date_format:Y-m-d'],
            'title' => ['sometimes', 'required', 'string', 'max:191'],
            'category' => ['sometimes', 'nullable', 'string', 'max:80'],
            'description' => ['sometimes', 'required', 'string', 'max:5000'],
            'actions' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'confidentiality_level' => ['sometimes', Rule::in(['general', 'reserved', 'restricted', 'high_confidentiality'])],
            'professional_staff_id' => ['sometimes', 'nullable', 'integer', 'exists:staff,id'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
