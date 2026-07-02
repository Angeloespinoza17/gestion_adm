<?php

namespace App\Http\Requests\Convivencia;

use App\Models\Convivencia\ConvivenciaAttachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadConvivenciaAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'category' => ['nullable', Rule::in(array_column(ConvivenciaAttachment::CATEGORY_OPTIONS, 'value'))],
            'confidentiality_level' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'document' => ['required', 'file', 'max:10240'],
        ];
    }
}
