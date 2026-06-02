<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to_dependency_id' => ['nullable', 'integer', 'exists:maintenance_dependencies,id'],
            'to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'movement_type' => ['required', 'string', 'max:191'],
            'movement_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string'],
        ];
    }
}

