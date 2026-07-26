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
            'is_disseminable' => ['sometimes', 'boolean'],
            'responsible_name' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string'],
            'document' => [
                $this->isMethod('post') ? 'required' : 'nullable',
                'file',
                'max:25600',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,csv,txt,jpg,jpeg,png,webp',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'document.required' => 'Debes adjuntar un archivo o tomar una fotografía.',
            'document.max' => 'El archivo no puede superar los 25 MB.',
            'document.mimes' => 'El formato seleccionado no está permitido.',
            'valid_until.after_or_equal' => 'La fecha de término debe ser igual o posterior al inicio de vigencia.',
        ];
    }
}
