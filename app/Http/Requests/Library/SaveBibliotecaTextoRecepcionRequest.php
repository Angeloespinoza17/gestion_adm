<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;

class SaveBibliotecaTextoRecepcionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'received_at' => ['required', 'date'],
            'source_name' => ['nullable', 'string', 'max:191'],
            'document_reference' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.biblioteca_texto_titulo_id' => ['nullable', 'integer', 'exists:biblioteca_texto_titulos,id'],
            'items.*.education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'items.*.title' => ['required', 'string', 'max:191'],
            'items.*.subject' => ['required', 'string', 'max:120'],
            'items.*.publisher' => ['nullable', 'string', 'max:191'],
            'items.*.isbn' => ['nullable', 'string', 'max:32'],
            'items.*.quantity_received' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }
}
