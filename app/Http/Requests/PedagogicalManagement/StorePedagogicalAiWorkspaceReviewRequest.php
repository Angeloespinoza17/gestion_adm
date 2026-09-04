<?php

namespace App\Http\Requests\PedagogicalManagement;

use Illuminate\Foundation\Http\FormRequest;

class StorePedagogicalAiWorkspaceReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('pedagogical-instruments.ai-workspace') === true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:lcd_schools,id'],
            'subject_id' => ['required', 'integer', 'exists:schedule_subjects,id'],
            'course_id' => ['required', 'integer', 'exists:course_sections,id'],
            'file' => [
                'required',
                'file',
                'mimes:pdf,docx',
                'max:'.(int) config('pedagogical_management.storage.max_file_kb', 30720),
            ],
        ];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        $applicationLimitMb = max(1, (int) ceil(
            (int) config('pedagogical_management.storage.max_file_kb', 30720) / 1024
        ));
        $runtimeLimit = trim((string) ini_get('upload_max_filesize')) ?: 'desconocido';

        return [
            'file.required' => 'Selecciona un archivo PDF o Word (.docx).',
            'file.uploaded' => "El servidor no recibió el archivo completo. El límite PHP actual es {$runtimeLimit}; el administrador debe ajustarlo para admitir archivos de hasta {$applicationLimitMb} MB.",
            'file.mimes' => 'Solo se permiten archivos PDF o Word (.docx).',
            'file.max' => "El archivo puede pesar como máximo {$applicationLimitMb} MB.",
        ];
    }

    /** @return array<string,string> */
    public function attributes(): array
    {
        return [
            'school_id' => 'establecimiento',
            'subject_id' => 'asignatura',
            'course_id' => 'curso',
            'file' => 'instrumento',
        ];
    }
}
