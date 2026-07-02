<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;

class SaveBibliotecaEspacioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'exists:biblioteca_espacios,id'],
            'name' => ['required', 'string', 'max:191'],
            'location' => ['nullable', 'string', 'max:191'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'resources' => ['nullable', 'array'],
            'resources.*' => ['string', 'max:120'],
            'active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
