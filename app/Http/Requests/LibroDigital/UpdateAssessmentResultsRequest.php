<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssessmentResultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.assessments.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'results' => ['required', 'array', 'min:1'],
            'results.*.student_profile_id' => ['required', 'integer', 'distinct', 'exists:student_profiles,id'],
            'results.*.raw_score' => ['nullable', 'numeric', 'min:0'],
            'results.*.numeric_value' => ['nullable', 'numeric', 'between:1,7'],
            'results.*.qualitative_value' => ['nullable', 'string', 'max:120'],
            'results.*.absent' => ['sometimes', 'boolean'],
            'results.*.exempt' => ['sometimes', 'boolean'],
            'results.*.observation' => ['nullable', 'string', 'max:2000'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
