<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ImportEdeStandardRequest extends FormRequest
{
    private const ALLOWED_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'application/zip',
        'application/pdf',
        'application/json',
        'text/plain',
        'text/csv',
        'application/xml',
        'text/xml',
        'image/svg+xml',
        'application/octet-stream',
    ];

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.ede.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:lcd_schools,id'],
            'version' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/'],
            'code' => ['sometimes', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
            'authority' => ['required', 'string', 'max:160'],
            'source_url' => ['required', 'url:http,https', 'max:2048'],
            'effective_from' => ['nullable', 'date_format:Y-m-d'],
            'source' => ['required', 'file', 'max:20480'],
            'schema' => ['required', 'file', 'max:20480'],
            'mappings' => ['required', 'file', 'max:20480'],
            // La activación es una operación separada y aprobada. Una carga HTTP
            // solamente deja artefactos importados para revisión.
            'activate' => ['prohibited'],
            'approval_reference' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (['source', 'schema', 'mappings'] as $field) {
                $file = $this->file($field);
                if ($file && ! in_array((string) $file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
                    $validator->errors()->add($field, 'El tipo real del archivo no está permitido para un artefacto EDE.');
                }
            }
            $mappings = $this->file('mappings');
            if ($mappings && strtolower($mappings->getClientOriginalExtension()) !== 'json') {
                $validator->errors()->add('mappings', 'El manifiesto declarativo de mapeos debe ser JSON.');
            }
        });
    }
}
