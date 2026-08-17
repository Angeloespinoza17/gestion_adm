<?php

namespace App\Http\Requests\LibroDigital;

use App\Models\PorterStudentWithdrawal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEarlyWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.withdrawals.manage')
            || $this->user()?->hasPermission('registrar_retiro_porteria')
            || false;
    }

    public function rules(): array
    {
        return [
            'student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'person_name' => ['required', 'string', 'max:191'],
            'person_rut' => ['sometimes', 'nullable', 'string', 'max:20'],
            'person_relationship' => ['required', Rule::in(array_column(PorterStudentWithdrawal::RELATIONSHIP_OPTIONS, 'value'))],
            'person_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'reason' => ['required', Rule::in(array_column(PorterStudentWithdrawal::REASON_OPTIONS, 'value'))],
            'observations' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'occurred_at' => ['sometimes', 'nullable', 'date'],
            'force_duplicate_confirmation' => ['sometimes', 'boolean'],
            'approve_override' => ['sometimes', 'boolean'],
            'override_reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
