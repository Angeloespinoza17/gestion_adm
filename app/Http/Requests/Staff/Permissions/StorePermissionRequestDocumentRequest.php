<?php

namespace App\Http\Requests\Staff\Permissions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionRequestDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document' => ['required', 'file', 'max:20480'],
            'comments' => ['nullable', 'string'],
        ];
    }
}
