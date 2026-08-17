<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.assessments.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:2000'],
            'assessment_type' => ['required', Rule::in(['diagnostic', 'formative', 'summative', 'recovery'])],
            'scheduled_on' => ['required', 'date_format:Y-m-d'],
            'weighting' => ['nullable', 'numeric', 'between:0,100'],
            'maximum_score' => ['nullable', 'numeric', 'gt:0'],
            'grading_scale' => ['required', Rule::in(['1_to_7'])],
            'curriculum_objective_ids' => ['sometimes', 'array'],
            'curriculum_objective_ids.*' => ['integer', 'distinct', 'exists:lcd_learning_objectives,id'],
        ];
    }
}
