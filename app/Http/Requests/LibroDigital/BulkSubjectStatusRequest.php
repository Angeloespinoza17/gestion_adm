<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;

class BulkSubjectStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.subject_catalog.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:lcd_schools,id'],
            'subject_ids' => ['required', 'array', 'min:1', 'max:200'],
            'subject_ids.*' => ['required', 'integer', 'distinct', 'exists:schedule_subjects,id'],
            'active' => ['required', 'boolean'],
        ];
    }
}
