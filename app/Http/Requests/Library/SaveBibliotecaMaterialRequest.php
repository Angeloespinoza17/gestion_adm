<?php

namespace App\Http\Requests\Library;

use App\Models\Library\BibliotecaEjemplar;
use App\Models\Library\BibliotecaObra;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBibliotecaMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $materialTypes = array_values(array_diff(
            BibliotecaObra::MATERIAL_TYPES,
            ['libro', 'diccionario', 'enciclopedia']
        ));

        return [
            'material_type' => ['required', Rule::in($materialTypes)],
            'title' => ['required', 'string', 'max:191'],
            'subtitle' => ['nullable', 'string', 'max:191'],
            'main_author' => ['nullable', 'string', 'max:191'],
            'publisher' => ['nullable', 'string', 'max:191'],
            'biblioteca_categoria_id' => ['nullable', 'integer', 'exists:biblioteca_categorias,id'],
            'description' => ['nullable', 'string'],
            'internal_code' => ['nullable', 'string', 'max:80', 'unique:biblioteca_obras,internal_code'],
            'barcode' => ['nullable', 'string', 'max:120', 'unique:biblioteca_obras,barcode'],
            'biblioteca_ubicacion_id' => ['nullable', 'integer', 'exists:biblioteca_ubicaciones,id'],
            'physical_location' => ['nullable', 'string', 'max:120'],
            'shelf' => ['nullable', 'string', 'max:120'],
            'section' => ['nullable', 'string', 'max:120'],
            'general_status' => ['required', Rule::in(BibliotecaObra::STATUS_OPTIONS)],
            'observations' => ['nullable', 'string'],
            'quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'ingress_date' => ['nullable', 'date'],
            'origin' => ['required', Rule::in(BibliotecaEjemplar::ORIGIN_OPTIONS)],
            'physical_state' => ['required', Rule::in(BibliotecaEjemplar::STATE_OPTIONS)],
        ];
    }
}
