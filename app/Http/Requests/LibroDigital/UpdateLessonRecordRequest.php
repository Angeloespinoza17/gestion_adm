<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLessonRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.lesson.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'objectives' => ['required', 'string', 'max:4000'],
            'contents' => ['required', 'string', 'max:5000'],
            'activities' => ['required', 'string', 'max:5000'],
            'methodology' => ['nullable', 'string', 'max:2000'],
            'resources' => ['nullable', 'string', 'max:2000'],
            'observations' => ['nullable', 'string', 'max:2000'],
            'curriculum_objective_ids' => ['nullable', 'array'],
            'curriculum_objective_ids.*' => ['integer', 'exists:lcd_learning_objectives,id'],
            'treatment_level' => ['nullable', Rule::in(['introduced', 'developing', 'consolidated', 'assessed'])],
            'progress_percentage' => ['nullable', 'integer', 'between:0,100'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
