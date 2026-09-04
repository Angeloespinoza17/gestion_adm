<?php

namespace App\Http\Requests\Supply;

use App\Models\Supply\SupplyItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplyItemRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'minimum_stock' => $this->input('minimum_stock') === '' ? null : $this->input('minimum_stock'),
            'supplier_id' => $this->input('supplier_id') === '' ? null : $this->input('supplier_id'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'storeroom_id' => [
                Rule::requiredIf(fn (): bool => $this->route('item')?->section === SupplyItem::SECTION_MAINTENANCE_STOREROOM),
                'nullable',
                'integer',
                Rule::exists('supply_storerooms', 'id')->where('active', true),
            ],
            'supply_type' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit_of_measure' => ['required', 'string', 'max:50'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'active' => ['required', 'boolean'],
        ];
    }
}
