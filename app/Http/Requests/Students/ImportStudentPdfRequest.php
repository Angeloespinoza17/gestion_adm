<?php

namespace App\Http\Requests\Students;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ImportStudentPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pdf' => ['required', File::types(['pdf'])->max(40 * 1024)],
            'course_section_id' => ['required', 'integer', 'exists:course_sections,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'pdf.required' => 'Debes seleccionar una ficha PDF.',
            'pdf.max' => 'El libro PDF no puede superar los 40 MB.',
            'course_section_id.required' => 'Debes seleccionar el curso de destino antes de importar el PDF.',
            'course_section_id.exists' => 'El curso seleccionado ya no está disponible.',
        ];
    }
}
