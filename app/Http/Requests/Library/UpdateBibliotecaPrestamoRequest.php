<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBibliotecaPrestamoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'due_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'pickup_person_type' => ['nullable', Rule::in(['student', 'guardian', 'staff', 'teacher', 'other'])],
            'pickup_person_name' => ['nullable', 'string', 'max:191'],
            'pickup_person_rut' => ['nullable', 'string', 'max:30'],
            'pickup_person_email' => ['nullable', 'email', 'max:191'],
            'pickup_person_relationship' => ['nullable', 'string', 'max:80'],
            'signature_data' => ['nullable', 'string', 'max:1500000'],
            'signature_name' => ['nullable', 'string', 'max:191'],
            'signature_rut' => ['nullable', 'string', 'max:30'],
            'delivery_notes' => ['nullable', 'string'],
        ];
    }
}
