<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExternalSubjectMappingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.subject_catalog.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:lcd_schools,id'],
            'source_system' => ['required', 'string', 'in:legacy_gradebook'],
            'mappings' => ['required', 'array', 'min:1', 'max:250'],
            'mappings.*.scope_code' => ['required', 'string', 'max:40'],
            'mappings.*.external_name' => ['required', 'string', 'max:255'],
            'mappings.*.schedule_subject_id' => ['required', 'integer', 'exists:schedule_subjects,id'],
        ];
    }
}
