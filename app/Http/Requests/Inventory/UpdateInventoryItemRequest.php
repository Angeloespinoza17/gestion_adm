<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],

            'category_id' => ['sometimes', 'integer', 'exists:inventory_categories,id'],
            'subcategory_id' => ['nullable', 'integer', 'exists:inventory_subcategories,id'],

            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],

            'purchase_date' => ['nullable', 'date'],
            'purchase_value' => ['nullable', 'integer', 'min:0'],
            'useful_life_years' => ['nullable', 'integer', 'min:0', 'max:100'],

            'status' => ['sometimes', 'string', 'max:191'],
            'condition' => ['sometimes', 'string', 'max:191'],

            'dependency_id' => ['nullable', 'integer', 'exists:maintenance_dependencies,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],

            'active' => ['sometimes', 'boolean'],

            'item_type' => ['sometimes', Rule::in(['asset', 'consumable'])],
            'stock_quantity' => ['nullable', 'numeric', 'min:0'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'unit_of_measure' => ['nullable', 'string', 'max:50'],

            // Foto principal opcional.
            'photo' => ['nullable', 'file', 'image', 'max:10240'],
        ];
    }
}

