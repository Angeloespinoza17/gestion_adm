<?php

namespace App\Http\Requests\RiskPrevention;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommitRiskImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('risk-matrix.import') ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['new_matrix', 'existing_matrix'])],
            'code' => ['required_if:type,new_matrix', 'nullable', 'string', 'max:80'],
            'folio' => ['nullable', 'string', 'max:80'], 'name' => ['required_if:type,new_matrix', 'nullable', 'string', 'max:255'],
            'work_center_id' => ['nullable', 'integer', 'exists:maintenance_dependencies,id'],
            'matrix_id' => ['required_if:type,existing_matrix', 'nullable', 'integer', 'exists:prevent_risk_matrices,id'],
            'version_id' => ['required_if:type,existing_matrix', 'nullable', 'integer', 'exists:prevent_risk_matrix_versions,id'],
            'skip_error_rows' => ['nullable', 'boolean'], 'confirm_duplicate_file' => ['nullable', 'boolean'],
        ];
    }
}
