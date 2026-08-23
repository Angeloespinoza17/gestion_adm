<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;

class ResolveCurriculumProgramConflictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.curriculum_programs.resolve_conflicts') === true;
    }

    public function rules(): array
    {
        return ['resolution' => ['required', 'string', 'min:3', 'max:3000']];
    }
}
