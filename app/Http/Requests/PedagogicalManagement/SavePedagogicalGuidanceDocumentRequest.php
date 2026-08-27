<?php

namespace App\Http\Requests\PedagogicalManagement;

use App\Models\PedagogicalManagement\PedagogicalGuidanceDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePedagogicalGuidanceDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('pedagogical-guidance.manage') === true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:lcd_schools,id'],
            'document_type' => ['required', Rule::in(PedagogicalGuidanceDocument::TYPES)],
            'title' => ['required', 'string', 'min:3', 'max:191'],
            'description' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string', 'min:10', 'max:50000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
