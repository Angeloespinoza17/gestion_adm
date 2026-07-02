<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnBibliotecaPrestamoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'returned_at' => ['nullable', 'date'],
            'received_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'returned_condition' => ['required', Rule::in(['bueno', 'regular', 'danado', 'perdido'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
