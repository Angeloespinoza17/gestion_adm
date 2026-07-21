<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePublicContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
            'correo' => trim((string) $this->input('correo')),
            'telefono' => trim((string) $this->input('telefono')),
            'asunto' => trim((string) $this->input('asunto')),
            'mensaje' => trim((string) $this->input('mensaje')),
        ]);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:191'],
            'correo' => ['required', 'email', 'max:191'],
            'telefono' => ['nullable', 'string', 'max:80'],
            'asunto' => ['required', 'string', 'max:191'],
            'mensaje' => ['required', 'string', 'max:5000'],
            'website' => ['nullable', 'prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Ingresa tu nombre.',
            'correo.required' => 'Ingresa tu correo electrónico.',
            'correo.email' => 'Ingresa un correo electrónico válido.',
            'asunto.required' => 'Ingresa el asunto.',
            'mensaje.required' => 'Ingresa tu mensaje.',
            'website.prohibited' => 'No fue posible enviar el mensaje.',
        ];
    }
}
