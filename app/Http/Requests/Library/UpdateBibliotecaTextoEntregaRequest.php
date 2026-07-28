<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBibliotecaTextoEntregaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['pendiente', 'parcial', 'entregado'])],
            'signature_data' => ['nullable', 'string', 'max:1500000'],
            'signature_name' => ['nullable', 'string', 'max:191'],
            'signature_rut' => ['nullable', 'string', 'max:30'],
            'pending_reason' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:biblioteca_texto_entrega_items,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'items.*.status' => ['required', Rule::in(['pendiente', 'entregado'])],
            'items.*.pending_reason' => ['nullable', 'string'],
        ];
    }
}
