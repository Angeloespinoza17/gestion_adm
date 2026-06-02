<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var InventoryCategory $category */
        $category = $this->route('category') ?? $this->route('inventoryCategory');

        $categoryId = $category instanceof InventoryCategory ? $category->id : null;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('inventory_categories', 'slug')->ignore($categoryId)],
            'code_prefix' => ['sometimes', 'string', 'max:10', Rule::unique('inventory_categories', 'code_prefix')->ignore($categoryId)],
            'description' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}

