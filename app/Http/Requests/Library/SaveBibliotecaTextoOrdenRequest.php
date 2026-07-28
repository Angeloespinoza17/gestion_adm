<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;

class SaveBibliotecaTextoOrdenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'course_section_id' => ['required', 'integer', 'exists:course_sections,id'],
            'prepared_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.biblioteca_texto_titulo_id' => ['nullable', 'integer', 'exists:biblioteca_texto_titulos,id'],
            'items.*.title' => ['required', 'string', 'max:191'],
            'items.*.subject' => ['required', 'string', 'max:120'],
            'items.*.quantity_required' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }
}
