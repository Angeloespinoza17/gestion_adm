<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidateEdeExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.ede.validate') ?? false;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['sometimes', 'nullable', 'integer', 'exists:lcd_schools,id'],
            // La API pública solo expone la verificación contractual final.
            // parse/insert quedan reservados al pipeline aislado y versionado.
            'operation' => ['sometimes', Rule::in(['check'])],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
