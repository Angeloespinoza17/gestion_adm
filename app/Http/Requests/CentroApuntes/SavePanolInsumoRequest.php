<?php

namespace App\Http\Requests\CentroApuntes;

use App\Http\Requests\CentroApuntes\Concerns\NormalizesNullableFields;
use App\Models\CentroApuntes\PanolInsumo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePanolInsumoRequest extends FormRequest
{
    use NormalizesNullableFields;

    protected function prepareForValidation(): void
    {
        $this->normalizeNullableFields([
            'maximum_stock',
            'location',
            'supplier_id',
            'last_purchase_at',
            'expires_at',
            'status',
            'observations',
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'category' => ['required', Rule::in(PanolInsumo::CATEGORY_OPTIONS)],
            'unit_of_measure' => ['required', Rule::in(PanolInsumo::UNIT_OPTIONS)],
            'current_stock' => ['required', 'numeric', 'min:0'],
            'minimum_stock' => ['required', 'numeric', 'min:0'],
            'maximum_stock' => ['nullable', 'numeric', 'gte:minimum_stock'],
            'location' => ['nullable', 'string', 'max:160'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'last_purchase_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(PanolInsumo::STATUS_OPTIONS)],
            'observations' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
