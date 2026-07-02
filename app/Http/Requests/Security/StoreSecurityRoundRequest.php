<?php

namespace App\Http\Requests\Security;

use Illuminate\Foundation\Http\FormRequest;

class StoreSecurityRoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payload' => ['required', 'string'],
            'evidence_files' => ['nullable', 'array'],
            'evidence_files.*' => ['file', 'image', 'max:5120'],
        ];
    }
}
