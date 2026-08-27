<?php

namespace App\Http\Requests\PedagogicalManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveValidationResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('pedagogical-instruments.resolve-validations') === true;
    }

    public function rules(): array
    {
        return [
            'resolution_status' => ['required', Rule::in(['accepted_exception', 'corrected', 'false_positive', 'not_applicable'])],
            'notes' => ['required', 'string', 'min:5', 'max:3000'],
        ];
    }
}
