<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class StoreStaffDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document' => ['required', 'file', 'max:20480'],
            'document_type' => ['nullable', 'string', 'max:191'],
            'observations' => ['nullable', 'string'],
        ];
    }
}
