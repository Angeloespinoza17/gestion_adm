<?php

namespace App\Http\Requests\LibroDigital;

use App\Http\Requests\LibroDigital\Concerns\ValidatesCurriculumEvidenceFiles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ValidateCurriculumImportRequest extends FormRequest
{
    use ValidatesCurriculumEvidenceFiles;

    private const ALLOWED_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
        'application/octet-stream',
    ];

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.curriculum.import') ?? false;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:lcd_schools,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'file' => ['required', 'file', 'max:20480'],
            ...$this->curriculumEvidenceFileRules(),
            // Validar, aprobar y activar son pasos separados. Ninguna columna
            // adicional de la solicitud puede omitir el flujo de gobernanza.
            'activate' => ['prohibited'],
            'approve' => ['prohibited'],
            'status' => ['prohibited'],
            'catalog_id' => ['prohibited'],
            'catalog_code' => ['prohibited'],
            'catalog_name' => ['prohibited'],
            'version' => ['prohibited'],
            'authority' => ['prohibited'],
            'source_url' => ['prohibited'],
            'source_sha256' => ['prohibited'],
            'effective_from' => ['prohibited'],
            'effective_to' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateCurriculumEvidenceFiles($validator);

        $validator->after(function (Validator $validator): void {
            $file = $this->file('file');
            if (! $file) {
                return;
            }

            if (mb_strtolower($file->getClientOriginalExtension()) !== 'xlsx') {
                $validator->errors()->add('file', 'El importador curricular solo acepta archivos XLSX.');
            }

            if (! in_array((string) $file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
                $validator->errors()->add('file', 'El tipo real del archivo no corresponde a un XLSX permitido.');
            }
        });
    }
}
