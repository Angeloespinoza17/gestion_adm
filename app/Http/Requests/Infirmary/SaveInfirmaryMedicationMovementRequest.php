<?php

namespace App\Http\Requests\Infirmary;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInfirmaryMedicationMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'movement_type' => ['required', Rule::in(['ingreso', 'salida', 'administracion', 'ajuste', 'perdida', 'vencimiento', 'donacion'])],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'adjustment_direction' => ['nullable', Rule::in(['increase', 'decrease'])],
            'reason' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string'],
            'moved_at' => ['nullable', 'date'],
        ];
    }
}
