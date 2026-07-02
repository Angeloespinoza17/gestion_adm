<?php

namespace App\Http\Requests\Infirmary;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadInfirmaryDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document' => ['required', 'file', 'max:20480'],
            'student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'category' => ['nullable', Rule::in(['pdf', 'imagen', 'fotografia', 'certificado_medico', 'receta', 'informe_medico', 'orden_atencion', 'autorizacion_medica', 'autorizacion_apoderado', 'otro'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
