<?php

namespace App\Http\Requests\Library;

use App\Models\Library\BibliotecaEjemplar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBibliotecaEjemplarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ejemplarId = $this->route('ejemplar')?->id;

        return [
            'biblioteca_obra_id' => ['required', 'integer', 'exists:biblioteca_obras,id'],
            'code' => ['required', 'string', 'max:80', Rule::unique('biblioteca_ejemplares', 'code')->ignore($ejemplarId)],
            'barcode' => ['nullable', 'string', 'max:120', Rule::unique('biblioteca_ejemplares', 'barcode')->ignore($ejemplarId)],
            'ingress_date' => ['nullable', 'date'],
            'origin' => ['required', Rule::in(BibliotecaEjemplar::ORIGIN_OPTIONS)],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'physical_location' => ['nullable', 'string', 'max:120'],
            'physical_state' => ['required', Rule::in(BibliotecaEjemplar::STATE_OPTIONS)],
            'availability_status' => ['required', Rule::in(BibliotecaEjemplar::AVAILABILITY_OPTIONS)],
            'registered_by' => ['nullable', 'integer', 'exists:users,id'],
            'observations' => ['nullable', 'string'],
            'photo_urls' => ['nullable', 'array'],
            'photo_urls.*' => ['string', 'max:2048'],
            'last_inventory_checked_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
