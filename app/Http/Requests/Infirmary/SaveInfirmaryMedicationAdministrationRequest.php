<?php

namespace App\Http\Requests\Infirmary;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInfirmaryMedicationAdministrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'authorization_id' => ['nullable', 'integer', 'exists:infirmary_medication_authorizations,id'],
            'attention_id' => ['nullable', 'integer', 'exists:infirmary_attentions,id'],
            'medication_id' => ['required', 'integer', 'exists:infirmary_medications,id'],
            'student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'administered_at' => ['required', 'date'],
            'administered_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'quantity_administered' => ['required', 'numeric', 'min:0.01'],
            'schedule_reference' => ['nullable', 'string', 'max:120'],
            'source_type' => ['nullable', Rule::in(['atencion', 'autorizacion'])],
            'observations' => ['nullable', 'string'],
        ];
    }
}
