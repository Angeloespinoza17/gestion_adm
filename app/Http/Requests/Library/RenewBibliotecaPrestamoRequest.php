<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;

class RenewBibliotecaPrestamoRequest extends FormRequest
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
        ];
    }
}
