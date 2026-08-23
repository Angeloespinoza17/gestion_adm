<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.subject_catalog.manage') ?? false;
    }

    public function rules(): array
    {
        $subject = $this->route('subject');
        $subjectId = is_object($subject) ? $subject->getKey() : (ctype_digit((string) $subject) ? (int) $subject : null);

        return [
            'name' => ['sometimes', 'string', 'max:191'],
            'code' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('schedule_subjects', 'code')->ignore($subjectId)],
            'area' => ['sometimes', 'nullable', 'string', 'max:100'],
            'color' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'active' => ['sometimes', 'boolean'],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'subject_type' => ['sometimes', 'nullable', Rule::in(['official', 'common_plan', 'differentiated', 'workshop', 'institutional'])],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'education_types' => ['sometimes', 'nullable', 'array', 'max:3'],
            'education_types.*' => ['string', 'distinct', Rule::in(['parvularia', 'basica', 'media'])],
            'official_code' => ['prohibited'],
            'type' => ['prohibited'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
