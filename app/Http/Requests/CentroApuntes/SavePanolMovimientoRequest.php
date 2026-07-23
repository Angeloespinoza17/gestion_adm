<?php

namespace App\Http\Requests\CentroApuntes;

use App\Http\Requests\CentroApuntes\Concerns\NormalizesNullableFields;
use App\Models\CentroApuntes\PanolMovimiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePanolMovimientoRequest extends FormRequest
{
    use NormalizesNullableFields;

    protected function prepareForValidation(): void
    {
        $this->normalizeNullableFields([
            'moved_at',
            'responsible_user_id',
            'requested_by_user_id',
            'department_id',
            'reason',
            'document_reference',
            'observations',
            'adjustment_mode',
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'insumo_id' => ['required', 'integer', 'exists:panol_insumos,id'],
            'movement_type' => ['required', Rule::in(PanolMovimiento::TYPE_OPTIONS)],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'moved_at' => ['nullable', 'date'],
            'responsible_user_id' => ['nullable', 'exists:users,id'],
            'requested_by_user_id' => ['nullable', 'exists:users,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'reason' => ['nullable', 'string', 'max:191'],
            'document_reference' => ['nullable', 'string', 'max:191'],
            'observations' => ['nullable', 'string'],
            'adjustment_mode' => ['nullable', 'required_if:movement_type,ajuste', Rule::in(['sumar', 'restar'])],
        ];
    }
}
