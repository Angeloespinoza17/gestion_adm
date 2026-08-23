<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewCurriculumProgramCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.curriculum_programs.review') === true;
    }

    public function rules(): array
    {
        return [
            'detected_value' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'structured_payload' => ['sometimes', 'array'],
            'suggested_existing_id' => ['sometimes', 'nullable', 'integer'],
            'suggested_action' => ['sometimes', Rule::in(['reuse', 'create', 'skip', 'review'])],
            'review_status' => ['sometimes', Rule::in(['accepted', 'rejected', 'skipped', 'pending'])],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
