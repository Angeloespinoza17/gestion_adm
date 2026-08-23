<?php

namespace App\Http\Requests\RiskPrevention;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRiskMatrixVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('risk-matrix.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:1'],
            'name' => ['sometimes', 'string', 'max:255'], 'folio' => ['nullable', 'string', 'max:80'], 'description' => ['nullable', 'string', 'max:5000'],
            'work_center_id' => ['nullable', 'integer', 'exists:maintenance_dependencies,id'], 'work_center_name' => ['nullable', 'string', 'max:255'],
            'company_name_snapshot' => ['sometimes', 'string', 'max:255'], 'company_tax_id_snapshot' => ['nullable', 'string', 'max:40'],
            'company_address_snapshot' => ['nullable', 'string', 'max:255'], 'economic_activity_code_snapshot' => ['nullable', 'string', 'max:80'],
            'work_center_name_snapshot' => ['nullable', 'string', 'max:255'], 'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'prepared_on' => ['nullable', 'date'], 'total_workers' => ['nullable', 'integer', 'min:0'],
            'program_responsible_id' => ['nullable', 'integer', 'exists:users,id'], 'program_responsible_name_snapshot' => ['nullable', 'string', 'max:255'],
            'program_responsible_position_snapshot' => ['nullable', 'string', 'max:255'], 'review_reason' => ['nullable', 'string', 'max:5000'],
            'review_notes' => ['nullable', 'string', 'max:5000'], 'effective_from' => ['nullable', 'date'], 'next_review_at' => ['nullable', 'date'],
            'legal_representative_id' => ['nullable', 'integer', 'exists:staff,id'], 'legal_representative_name_snapshot' => ['nullable', 'string', 'max:255'],
            'legal_representative_position_snapshot' => ['nullable', 'string', 'max:255'], 'change_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
