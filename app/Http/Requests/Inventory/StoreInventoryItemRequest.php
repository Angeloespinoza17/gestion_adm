<?php

namespace App\Http\Requests\Inventory;

use App\Models\MaintenanceDependency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryItemRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $nullableFields = [
            'description',
            'subcategory_id',
            'brand',
            'model',
            'serial_number',
            'purchase_date',
            'purchase_value',
            'useful_life_years',
            'dependency_id',
            'responsible_user_id',
            'supplier_id',
            'stock_quantity',
            'minimum_stock',
            'unit_of_measure',
            'warranty_months',
            'warranty_expires_at',
        ];

        $payload = [];

        foreach ($nullableFields as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $payload[$field] = null;
            }
        }

        if ($this->has('has_warranty')) {
            $payload['has_warranty'] = $this->boolean('has_warranty');
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],

            'category_id' => ['required', 'integer', 'exists:inventory_categories,id'],
            'subcategory_id' => ['nullable', 'integer', 'exists:inventory_subcategories,id'],

            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],

            'purchase_date' => [
                Rule::requiredIf(fn () => $this->boolean('has_warranty')),
                'nullable',
                'date',
            ],
            'purchase_value' => ['nullable', 'integer', 'min:0'],
            'useful_life_years' => ['nullable', 'integer', 'min:0', 'max:100'],
            'has_warranty' => ['sometimes', 'boolean'],
            'warranty_months' => [
                Rule::requiredIf(fn () => $this->boolean('has_warranty')),
                'nullable',
                'integer',
                'min:1',
                'max:600',
            ],
            'warranty_expires_at' => ['nullable', 'date'],

            'status' => ['sometimes', 'string', 'max:191'],
            'condition' => ['sometimes', 'string', 'max:191'],

            'dependency_id' => [
                'nullable',
                'integer',
                Rule::exists('maintenance_dependencies', 'id')
                    ->where('dependency_kind', MaintenanceDependency::KIND_SPACE)
                    ->where('active', true),
            ],
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
