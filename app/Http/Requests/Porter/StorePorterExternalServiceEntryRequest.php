<?php

namespace App\Http\Requests\Porter;

use App\Support\Rut;
use Illuminate\Foundation\Http\FormRequest;

class StorePorterExternalServiceEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'contact_rut' => Rut::normalize($this->input('contact_rut')),
            'vehicle_plate' => $this->filled('vehicle_plate') ? strtoupper(trim((string) $this->input('vehicle_plate'))) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'service_type' => ['required', 'string', 'max:191'],
            'company_name' => ['nullable', 'string', 'max:191'],
            'contact_name' => ['required', 'string', 'max:191'],
            'contact_rut' => ['nullable', 'string', 'max:20', function ($attribute, $value, $fail) {
                if ($value !== null && $value !== '' && !Rut::isValid($value)) {
                    $fail('El RUT ingresado no es válido.');
                }
            }],
            'phone' => ['nullable', 'string', 'max:50'],
            'vehicle_plate' => ['nullable', 'string', 'max:20'],
            'responsible_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'maintenance_dependency_id' => ['nullable', 'integer', 'exists:maintenance_dependencies,id'],
            'observations' => ['nullable', 'string'],
        ];
    }
}
