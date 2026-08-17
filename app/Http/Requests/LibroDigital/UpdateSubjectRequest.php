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
            'official_code' => ['prohibited'],
            'type' => ['prohibited'],
            'description' => ['prohibited'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
