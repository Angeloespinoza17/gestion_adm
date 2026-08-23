<?php

namespace App\Http\Requests\RiskPrevention;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRiskMatrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('risk-matrix.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:80', Rule::unique('prevent_risk_matrices')->where('company_key', config('risk_matrix.company.key'))],
            'folio' => ['nullable', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_tax_id' => ['nullable', 'string', 'max:40'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'economic_activity_code' => ['nullable', 'string', 'max:80'],
            'work_center_id' => ['nullable', 'integer', 'exists:maintenance_dependencies,id'],
            'work_center_name' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'methodology_id' => ['nullable', 'integer', 'exists:prevent_risk_methodologies,id'],
            'prepared_on' => ['nullable', 'date'],
            'total_workers' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'program_responsible_id' => ['nullable', 'integer', 'exists:users,id'],
            'program_responsible_name' => ['nullable', 'string', 'max:255'],
            'program_responsible_position' => ['nullable', 'string', 'max:255'],
            'legal_representative_id' => ['nullable', 'integer', 'exists:staff,id'],
            'legal_representative_name' => ['nullable', 'string', 'max:255'],
            'legal_representative_position' => ['nullable', 'string', 'max:255'],
        ];
    }
}
