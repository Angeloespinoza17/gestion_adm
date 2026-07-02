<?php

namespace App\Http\Requests\Porter;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePorterExternalServiceEntryStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['finalizado', 'rechazado'])],
            'observations' => ['nullable', 'string'],
        ];
    }
}
