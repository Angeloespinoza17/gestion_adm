<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaintenanceReportRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(['Sin comenzar', 'En proceso', 'En espera', 'Pausado', 'Terminado', 'Anulado'])],
            'priority' => ['nullable', Rule::in(['Crítico', 'Alta', 'Media', 'Baja'])],
            'assignee' => ['nullable', 'string', 'max:255'],
            'dependency_id' => ['nullable', 'integer', 'exists:maintenance_dependencies,id'],
            'technical_area_id' => ['nullable', 'integer', 'exists:maintenance_dependencies,id'],
        ];
    }
}
