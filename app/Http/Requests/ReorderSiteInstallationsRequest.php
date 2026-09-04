<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderSiteInstallationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id' => ['required', 'integer', 'distinct', 'exists:site_installations,id'],
            'items.*.sort_order' => [
                'required',
                'integer',
                'min:0',
                'max:100000',
                'distinct',
            ],
        ];
    }
}
