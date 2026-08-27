<?php

namespace App\Http\Requests\RiskPrevention;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRiskPreventionEppItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inventory_item_id' => [
                'nullable',
                'integer',
                Rule::exists('inventory_items', 'id')->where(
                    fn ($query) => $query->where('item_type', 'consumable')->where('active', true),
                ),
                Rule::unique('prevent_epp_items', 'inventory_item_id')->ignore($this->route('eppItem')?->id),
            ],
            'name' => ['required', 'string', 'max:160'],
            'epp_type' => ['required', 'string', 'max:120'],
            'stock' => ['required', 'integer', 'min:0'],
            'minimum_stock' => ['required', 'integer', 'min:0'],
            'unit' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
