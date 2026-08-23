<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UploadCurriculumProgramDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $fileCount = count((array) $this->file('files', []));

        return $user?->hasPermission('libro_digital.curriculum_programs.import') === true
            && ($fileCount <= 1 || $user->hasPermission('libro_digital.curriculum_programs.import_batch'));
    }

    public function rules(): array
    {
        return [
            'school_id' => ['nullable', 'integer'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'files' => ['required', 'array', 'min:1', 'max:30'],
            'files.*' => ['required', File::types(['pdf'])->max((int) config('libro_digital.curriculum_import.max_pdf_kb', 20480))],
            'schedule_subject_id' => ['nullable', 'integer', 'exists:schedule_subjects,id'],
            'education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'document_type' => ['nullable', 'string', 'max:60'],
            'title' => ['nullable', 'string', 'max:190'],
            'decree' => ['nullable', 'string', 'max:160'],
            'edition' => ['nullable', 'string', 'max:100'],
            'publication_year' => ['nullable', 'integer', 'between:1900,2200'],
            'official_url' => ['nullable', 'url', 'max:2000'],
            'modality_code' => ['nullable', 'string', 'max:60'],
            'formation_type_code' => ['nullable', 'string', 'max:60'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $applicationLimitMb = max(1, (int) ceil(
            (int) config('libro_digital.curriculum_import.max_pdf_kb', 20480) / 1024
        ));
        $runtimeLimit = trim((string) ini_get('upload_max_filesize')) ?: 'desconocido';

        return [
            'files.required' => 'Selecciona al menos un documento PDF.',
            'files.array' => 'La carga de documentos no tiene un formato válido.',
            'files.min' => 'Selecciona al menos un documento PDF.',
            'files.max' => 'Puedes cargar hasta 30 documentos PDF por lote.',
            'files.*.required' => 'No se recibió uno de los documentos seleccionados.',
            'files.*.uploaded' => "El servidor no recibió el PDF completo. El límite PHP actual es {$runtimeLimit}; el administrador debe ajustar upload_max_filesize y post_max_size para admitir documentos de hasta {$applicationLimitMb} MB.",
            'files.*.mimes' => 'Cada documento debe ser un PDF válido.',
            'files.*.max' => "Cada PDF puede pesar como máximo {$applicationLimitMb} MB.",
        ];
    }
}
