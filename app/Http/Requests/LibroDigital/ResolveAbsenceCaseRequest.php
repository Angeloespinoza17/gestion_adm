<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;

class ResolveAbsenceCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.absence.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'resolution' => ['required', 'string', 'max:5000'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
