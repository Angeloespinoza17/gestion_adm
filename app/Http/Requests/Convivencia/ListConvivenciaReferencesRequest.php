<?php

namespace App\Http\Requests\Convivencia;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListConvivenciaReferencesRequest extends FormRequest
{
    public const TYPES = [
        'cases',
        'complaints',
        'parts',
        'plans',
        'protocols',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => $this->route('type'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(self::TYPES)],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'selected_id' => ['nullable', 'integer', 'min:1'],
            'include_inactive' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.in' => 'El tipo de referencia solicitado no es válido.',
            'search.max' => 'La búsqueda no puede superar los 120 caracteres.',
            'per_page.max' => 'Puede solicitar como máximo 50 referencias por página.',
        ];
    }
}
