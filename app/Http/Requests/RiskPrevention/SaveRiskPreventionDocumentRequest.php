<?php

namespace App\Http\Requests\RiskPrevention;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRiskPreventionDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', Rule::in(['protocolo', 'reglamento', 'instructivo', 'informe'])],
            'title' => ['required', 'string', 'max:180'],
            'document_group' => ['nullable', 'string', 'max:120'],
            'version_number' => ['required', 'string', 'max:30'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'status' => ['required', Rule::in(['vigente', 'por_vencer', 'vencido', 'archivado'])],
            'responsible_name' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string'],
            'document' => ['nullable', 'file', 'max:10240'],
        ];
    }
}
