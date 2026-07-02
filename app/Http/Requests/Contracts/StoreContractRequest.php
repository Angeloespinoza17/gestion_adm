<?php

namespace App\Http\Requests\Contracts;

use App\Models\Contract;
use App\Models\Staff;
use App\Support\DateInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [
            'start_date' => DateInput::normalize($this->input('start_date')),
            'end_date' => DateInput::normalize($this->input('end_date')),
            'signature_date' => DateInput::normalize($this->input('signature_date')),
        ];

        foreach (['department_ids', 'signer_ids', 'custom_variables'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                $data[$field] = is_array($decoded) ? $decoded : [];
            }
        }

        if (($this->input('contract_type') ?: null) === 'indefinido') {
            $data['end_date'] = null;
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'contract_template_id' => ['required', 'integer', 'exists:contract_templates,id'],
            'contract_type' => ['nullable', Rule::in(array_column(Staff::CONTRACT_TYPE_OPTIONS, 'value'))],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'position_name' => ['nullable', 'string', 'max:255'],
            'contract_hours' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'workday' => ['nullable', Rule::in(array_column(Staff::WORKDAY_OPTIONS, 'value'))],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'string'],
            'place_of_signature' => ['nullable', 'string', 'max:255'],
            'signature_date' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(array_column(Contract::STATUS_OPTIONS, 'value'))],
            'rendered_content' => ['nullable', 'string'],
            'department_ids' => ['sometimes', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
            'signer_ids' => ['sometimes', 'array'],
            'signer_ids.*' => ['integer', 'exists:contract_signers,id'],
            'custom_variables' => ['nullable', 'array'],
            'observations' => ['nullable', 'string'],
        ];
    }
}
