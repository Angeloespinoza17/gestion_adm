<?php

namespace App\Http\Requests\Informatica;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnItEquipmentLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'returned_at' => ['nullable', 'date'],
            'received_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'return_condition' => ['required', Rule::in(['bueno', 'con_observaciones', 'danado', 'incompleto'])],
            'post_return_status' => ['nullable', Rule::in(['disponible', 'en_mantencion', 'danado'])],
            'return_notes' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:20480'],
        ];
    }
}
