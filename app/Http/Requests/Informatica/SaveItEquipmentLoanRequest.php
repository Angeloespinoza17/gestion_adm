<?php

namespace App\Http\Requests\Informatica;

use App\Models\It\ItEquipmentLoan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveItEquipmentLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'loan_code' => ['nullable', 'string', 'max:80', Rule::unique('it_equipment_loans', 'loan_code')],
            'it_equipment_id' => ['required', 'integer', 'exists:it_equipment,id'],
            'requester_type' => ['nullable', Rule::in(ItEquipmentLoan::REQUESTER_TYPES)],
            'requester_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'requester_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'requester_student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'requester_name' => ['nullable', 'string', 'max:191'],
            'requester_rut' => ['nullable', 'string', 'max:40'],
            'requester_contact' => ['nullable', 'string', 'max:191'],
            'borrowed_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', Rule::when($this->filled('borrowed_at'), ['after_or_equal:borrowed_at'])],
            'purpose' => ['nullable', 'string'],
            'location_name' => ['nullable', 'string', 'max:191'],
            'delivered_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:20480'],
        ];
    }
}
