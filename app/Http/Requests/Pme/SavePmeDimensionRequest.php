<?php

namespace App\Http\Requests\Pme;

use Illuminate\Foundation\Http\FormRequest;

class SavePmeDimensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
