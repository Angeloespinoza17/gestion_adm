<?php

namespace App\Http\Requests\Infirmary;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInfirmaryMedicationAuthorizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'medication_id' => ['required', 'integer', 'exists:infirmary_medications,id'],
            'diagnosis' => ['nullable', 'string'],
            'dose' => ['required', 'string', 'max:120'],
            'frequency' => ['nullable', 'string', 'max:120'],
            'schedule_text' => ['nullable', 'string', 'max:191'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'physician_name' => ['nullable', 'string', 'max:160'],
            'medical_authorization_expires_at' => ['nullable', 'date'],
            'guardian_authorization_expires_at' => ['nullable', 'date'],
            'observations' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['vigente', 'proxima_a_vencer', 'vencida', 'terminada'])],
        ];
    }
}
