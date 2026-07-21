<?php

namespace App\Http\Requests\Porter;

use App\Support\Rut;
use Illuminate\Foundation\Http\FormRequest;

class StorePorterKeyLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $requesterRut = $this->input('requester_rut');
        $requesterRut = is_null($requesterRut) ? null : trim((string) $requesterRut);

        $this->merge([
            'requester_rut' => $requesterRut === null || $requesterRut === ''
                ? null
                : (Rut::normalize($requesterRut) ?: $requesterRut),
        ]);
    }

    public function rules(): array
    {
        return [
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'maintenance_dependency_id' => ['nullable', 'integer', 'exists:maintenance_dependencies,id'],
            'requester_name' => ['required', 'string', 'max:191'],
            'requester_rut' => ['nullable', 'string', 'max:20'],
            'purpose' => ['nullable', 'string', 'max:191'],
            'expected_return_at' => ['nullable', 'date'],
            'observations' => ['nullable', 'string'],
        ];
    }
}
