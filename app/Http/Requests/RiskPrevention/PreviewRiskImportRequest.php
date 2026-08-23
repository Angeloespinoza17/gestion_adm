<?php

namespace App\Http\Requests\RiskPrevention;

use Illuminate\Foundation\Http\FormRequest;

class PreviewRiskImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('risk-matrix.import') ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip', 'max:'.config('risk_matrix.import.max_kilobytes')],
            'work_center_id' => ['nullable', 'integer', 'exists:maintenance_dependencies,id'],
        ];
    }
}
