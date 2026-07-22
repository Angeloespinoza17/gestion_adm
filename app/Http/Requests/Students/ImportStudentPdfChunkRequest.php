<?php

namespace App\Http\Requests\Students;

use App\Services\Students\StudentPdfChunkUploadService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ImportStudentPdfChunkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'upload_id' => ['required', 'string', 'regex:/^[a-zA-Z0-9-]{20,64}$/'],
            'chunk_index' => ['required', 'integer', 'min:0', 'max:'.(StudentPdfChunkUploadService::MAX_CHUNKS - 1)],
            'chunk_total' => ['required', 'integer', 'min:1', 'max:'.StudentPdfChunkUploadService::MAX_CHUNKS],
            'file_name' => ['required', 'string', 'max:255', 'regex:/\.pdf$/i'],
            'file_size' => ['required', 'integer', 'min:1', 'max:'.StudentPdfChunkUploadService::MAX_FILE_BYTES],
            'course_section_id' => ['required', 'integer', 'exists:course_sections,id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $fileSize = $this->integer('file_size');
            $chunkIndex = $this->integer('chunk_index');
            $chunkTotal = $this->integer('chunk_total');
            $expectedTotal = (int) ceil($fileSize / StudentPdfChunkUploadService::MAX_CHUNK_BYTES);
            $expectedBytes = $chunkIndex === $chunkTotal - 1
                ? $fileSize - ($chunkIndex * StudentPdfChunkUploadService::MAX_CHUNK_BYTES)
                : StudentPdfChunkUploadService::MAX_CHUNK_BYTES;

            if ($chunkTotal !== $expectedTotal || $chunkIndex >= $chunkTotal) {
                $validator->errors()->add('pdf', 'La secuencia de carga del PDF no es válida. Vuelve a seleccionar el archivo.');
            }

            if (strlen($this->getContent()) !== $expectedBytes) {
                $validator->errors()->add('pdf', 'Un fragmento del PDF llegó incompleto. Intenta importar el archivo nuevamente.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'file_name.regex' => 'El archivo debe tener extensión PDF.',
            'file_size.max' => 'El libro PDF no puede superar los 100 MB.',
            'course_section_id.required' => 'Debes seleccionar el curso de destino antes de importar el PDF.',
            'course_section_id.exists' => 'El curso seleccionado ya no está disponible.',
        ];
    }
}
