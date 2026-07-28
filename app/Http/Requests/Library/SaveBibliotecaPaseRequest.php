<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;

class SaveBibliotecaPaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'professor_staff_id' => ['required', 'integer', 'exists:staff,id'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after:valid_from'],
            'reason' => ['required', 'string', 'max:1000'],
            'regulation_version' => ['nullable', 'string', 'max:80'],
            'signature_data' => ['nullable', 'string', 'max:1500000'],
            'signature_name' => ['nullable', 'string', 'max:191'],
            'signature_rut' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
