<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;

class TransitionCurriculumProgramImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:3000'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'unit_id' => ['nullable', 'string', 'max:40', 'regex:/^(?:[0-9]+|[0-9A-HJKMNP-TV-Z]{26})$/i'],
        ];
    }
}
