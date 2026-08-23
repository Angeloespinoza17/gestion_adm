<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.assessments.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'assessment_type' => ['sometimes', Rule::in(['diagnostic', 'formative', 'summative', 'recovery'])],
            'scheduled_on' => ['sometimes', 'date_format:Y-m-d'],
            'weighting' => ['sometimes', 'nullable', 'numeric', 'between:0,100'],
            'maximum_score' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'grading_scale' => ['sometimes', Rule::in(['1_to_7'])],
            'curriculum_objective_ids' => ['sometimes', 'array'],
            'curriculum_objective_ids.*' => ['integer', 'distinct', 'exists:lcd_learning_objectives,id'],
            'curriculum_program_id' => ['sometimes', 'nullable', 'string', 'max:40', 'regex:/^(?:[0-9]+|[0-9A-HJKMNP-TV-Z]{26})$/i'],
            'curriculum_unit_id' => ['sometimes', 'nullable', 'string', 'max:40', 'regex:/^(?:[0-9]+|[0-9A-HJKMNP-TV-Z]{26})$/i'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
