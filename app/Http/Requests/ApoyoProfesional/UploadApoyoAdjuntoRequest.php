<?php

namespace App\Http\Requests\ApoyoProfesional;

use App\Models\ApoyoProfesional\ApoyoAdjunto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadApoyoAdjuntoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document' => ['required', 'file', 'max:20480'],
            'student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'category' => ['nullable', Rule::in(array_column(ApoyoAdjunto::CATEGORY_OPTIONS, 'value'))],
            'confidentiality_level' => ['nullable', Rule::in(['general', 'reservada', 'confidencial', 'alta_confidencialidad'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
