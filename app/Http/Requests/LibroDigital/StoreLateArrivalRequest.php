<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;

class StoreLateArrivalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.parvularia.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'arrival_at' => ['required', 'date'],
            'class_session_id' => ['sometimes', 'nullable'],
            'period_id' => ['sometimes', 'nullable'],
            'justification' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'source' => ['sometimes', 'string', 'in:manual,session_attendance,porter'],
        ];
    }
}
