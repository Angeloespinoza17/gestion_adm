<?php

namespace App\Http\Requests\Staff\Permissions;

use App\Models\PermissionRequestDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidatePermissionRequestDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'validation_status' => ['required', Rule::in(array_column(PermissionRequestDocument::VALIDATION_STATUS_OPTIONS, 'value'))],
            'comments' => ['nullable', 'string'],
        ];
    }
}
