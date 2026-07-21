<?php

namespace App\Http\Requests\Porter;

use App\Models\PorterStudentWithdrawal;
use App\Support\Rut;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePorterStudentWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $personRut = $this->input('person_rut');
        $personRut = is_string($personRut) ? trim($personRut) : null;

        $payload = [
            'person_rut' => $personRut === '' ? null : (Rut::normalize($personRut) ?: $personRut),
            'force_duplicate_confirmation' => $this->boolean('force_duplicate_confirmation'),
            'approve_override' => $this->boolean('approve_override'),
        ];

        $this->merge($payload);
    }

    public function rules(): array
    {
        return [
            'student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'person_name' => ['required', 'string', 'max:191'],
            'person_rut' => ['nullable', 'string', 'max:20'],
            'person_relationship' => ['required', Rule::in(array_column(PorterStudentWithdrawal::RELATIONSHIP_OPTIONS, 'value'))],
            'person_phone' => ['nullable', 'string', 'max:50'],
            'reason' => ['required', Rule::in(array_column(PorterStudentWithdrawal::REASON_OPTIONS, 'value'))],
            'observations' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf'],
            'force_duplicate_confirmation' => ['sometimes', 'boolean'],
            'approve_override' => ['sometimes', 'boolean'],
            'override_reason' => ['nullable', 'string'],
        ];
    }
}
