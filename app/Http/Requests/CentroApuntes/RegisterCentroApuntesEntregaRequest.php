<?php

namespace App\Http\Requests\CentroApuntes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterCentroApuntesEntregaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'received_by_user_id' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('user_type', 'staff')->where('active', true))],
            'notes' => ['nullable', 'string'],
        ];
    }
}
