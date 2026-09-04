<?php

namespace App\Http\Requests\Supply;

use App\Models\Supply\SupplyItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplyReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section' => ['required', Rule::in(SupplyItem::sections())],
            'storeroom_id' => [
                Rule::requiredIf(fn (): bool => $this->input('section') === SupplyItem::SECTION_MAINTENANCE_STOREROOM),
                'nullable',
                'integer',
                Rule::exists('supply_storerooms', 'id')->where('active', true),
            ],
            'purchased_at' => ['required', 'date'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'document_type' => ['nullable', 'string', 'max:40'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'total_amount' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.supply_item_id' => ['required', 'integer', 'distinct', 'exists:supply_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'items.*.unit_cost' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
