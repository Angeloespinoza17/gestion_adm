<?php

namespace App\Http\Requests\CentroApuntes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePanolEntregaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requested_by_user_id' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('user_type', 'staff')->where('active', true))],
            'withdrawn_by_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('user_type', 'staff')->where('active', true))],
            'department_id' => ['nullable', 'exists:departments,id'],
            'requested_at' => ['nullable', 'date'],
            'observations' => ['nullable', 'string'],
            'receipt_notes' => ['nullable', 'string'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.insumo_id' => ['required', 'integer', 'exists:panol_insumos,id'],
            'details.*.quantity' => ['required', 'numeric', 'gt:0'],
            'details.*.notes' => ['nullable', 'string'],
        ];
    }
}
