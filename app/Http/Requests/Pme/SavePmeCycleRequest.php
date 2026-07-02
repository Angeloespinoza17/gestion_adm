<?php

namespace App\Http\Requests\Pme;

use Illuminate\Foundation\Http\FormRequest;

class SavePmeCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'observations' => ['nullable', 'string'],
        ];
    }
}
