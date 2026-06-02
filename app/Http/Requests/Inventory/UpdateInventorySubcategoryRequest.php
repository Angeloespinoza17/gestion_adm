<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventorySubcategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventorySubcategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var InventorySubcategory $subcategory */
        $subcategory = $this->route('subcategory') ?? $this->route('inventorySubcategory');

        $subcategoryId = $subcategory instanceof InventorySubcategory ? $subcategory->id : null;
        $categoryId = $subcategory instanceof InventorySubcategory ? $subcategory->category_id : null;

        return [
            'category_id' => ['sometimes', 'integer', 'exists:inventory_categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('inventory_subcategories', 'slug')
                    ->where(fn ($query) => $query->where('category_id', $this->input('category_id', $categoryId)))
                    ->ignore($subcategoryId),
            ],
            'description' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}

