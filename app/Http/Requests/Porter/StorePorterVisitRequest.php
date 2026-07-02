<?php

namespace App\Http\Requests\Porter;

use App\Support\Rut;
use Illuminate\Foundation\Http\FormRequest;

class StorePorterVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'visitor_rut' => Rut::normalize($this->input('visitor_rut')),
        ]);
    }

    public function rules(): array
    {
        return [
            'visitor_name' => ['required', 'string', 'max:191'],
            'visitor_rut' => ['nullable', 'string', 'max:20', function ($attribute, $value, $fail) {
                if ($value !== null && $value !== '' && !Rut::isValid($value)) {
                    $fail('El RUT ingresado no es válido.');
                }
            }],
            'purpose' => ['required', 'string', 'max:191'],
            'visited_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'visited_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'visited_person_label' => ['nullable', 'string', 'max:191'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'observations' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (
                !$this->filled('visited_staff_id')
                && !$this->filled('visited_department_id')
                && !$this->filled('visited_person_label')
            ) {
                $validator->errors()->add('visited_person_label', 'Debes indicar a quién visita o el área visitada.');
            }
        });
    }
}
