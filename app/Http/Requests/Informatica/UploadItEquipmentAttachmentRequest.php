<?php

namespace App\Http\Requests\Informatica;

use App\Models\It\ItEquipmentAttachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadItEquipmentAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document' => ['required', 'file', 'max:20480'],
            'category' => ['nullable', Rule::in(ItEquipmentAttachment::CATEGORY_OPTIONS)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
