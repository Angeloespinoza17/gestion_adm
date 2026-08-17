<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;

class StoreAbsenceActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.absence.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:5000'],
            // Browsers send RFC3339 either with Z or an explicit offset and may
            // include milliseconds; Laravel's date validator accepts both.
            'occurred_at' => ['required', 'date'],
            'action_type' => ['sometimes', 'nullable', 'string', 'max:80'],
            'medium' => ['sometimes', 'nullable', 'string', 'max:60'],
            'result_code' => ['sometimes', 'nullable', 'string', 'max:80'],
            'next_deadline_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
