<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.subject_catalog.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:lcd_schools,id'],
            'name' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:50', 'unique:schedule_subjects,code'],
            'area' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'active' => ['nullable', 'boolean'],
            'official_code' => ['prohibited'],
            'type' => ['prohibited'],
            'description' => ['prohibited'],
        ];
    }
}
