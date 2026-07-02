<?php

namespace App\Http\Requests\CentroApuntes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CentroApuntesReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::in(['diario', 'semanal', 'mensual', 'semestral', 'anual'])],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'requested_by_user_id' => ['nullable', 'exists:users,id'],
            'subject_id' => ['nullable', 'exists:centro_apuntes_asignaturas,id'],
            'machine_id' => ['nullable', 'exists:centro_apuntes_maquinas,id'],
            'paper_size' => ['nullable', 'string', 'max:40'],
            'task_type' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'string', 'max:60'],
            'supply_id' => ['nullable', 'exists:panol_insumos,id'],
            'category' => ['nullable', 'string', 'max:80'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'urgent_only' => ['nullable', 'boolean'],
            'immediate_only' => ['nullable', 'boolean'],
        ];
    }
}
