<?php

namespace App\Http\Requests\PedagogicalManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class StorePedagogicalInstrumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('pedagogical-instruments.create') === true;
    }

    public function rules(): array
    {
        $fileRules = ['file', 'mimes:pdf,docx', 'max:'.(int) config('pedagogical_management.storage.max_file_kb', 20480)];

        return [
            'school_id' => ['required', 'integer', 'exists:lcd_schools,id'],
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
            'subject_id' => ['required', 'integer', 'exists:schedule_subjects,id'],
            'course_id' => ['required', 'integer', 'exists:course_sections,id'],
            'file' => ['required_without:pdf', ...$fileRules],
            'pdf' => ['required_without:file', ...$fileRules],
        ];
    }

    public function instrumentFile(): UploadedFile
    {
        return $this->file('file') ?? $this->file('pdf');
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('file')) {
                $validator->errors()->forget('pdf');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $applicationLimitMb = max(1, (int) ceil(
            (int) config('pedagogical_management.storage.max_file_kb', 20480) / 1024
        ));
        $runtimeLimit = trim((string) ini_get('upload_max_filesize')) ?: 'desconocido';

        return [
            'file.required_without' => 'Selecciona un archivo PDF o Word (.docx).',
            'pdf.required_without' => 'Selecciona un archivo PDF o Word (.docx).',
            'file.uploaded' => "El servidor no recibió el archivo completo. El límite PHP actual es {$runtimeLimit}; el administrador debe ajustar upload_max_filesize y post_max_size para admitir archivos de hasta {$applicationLimitMb} MB.",
            'pdf.uploaded' => "El servidor no recibió el archivo completo. El límite PHP actual es {$runtimeLimit}; el administrador debe ajustar upload_max_filesize y post_max_size para admitir archivos de hasta {$applicationLimitMb} MB.",
            'file.file' => 'El archivo seleccionado no tiene un formato de carga válido.',
            'pdf.file' => 'El archivo seleccionado no tiene un formato de carga válido.',
            'file.mimes' => 'Solo se permiten archivos PDF o Word (.docx).',
            'pdf.mimes' => 'Solo se permiten archivos PDF o Word (.docx).',
            'file.max' => "El archivo puede pesar como máximo {$applicationLimitMb} MB.",
            'pdf.max' => "El archivo puede pesar como máximo {$applicationLimitMb} MB.",
        ];
    }

    public function attributes(): array
    {
        return [
            'owner_user_id' => 'docente',
            'subject_id' => 'asignatura',
            'course_id' => 'curso',
            'file' => 'instrumento PDF o Word',
            'pdf' => 'instrumento PDF o Word',
        ];
    }
}
