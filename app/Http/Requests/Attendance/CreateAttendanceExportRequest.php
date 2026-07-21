<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAttendanceExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'report_type' => ['required', Rule::in(['executive', 'students', 'courses', 'risk', 'alerts', 'interventions', 'goals', 'financial', 'data_quality'])],
            'format' => ['required', Rule::in(['pdf', 'xls', 'csv'])],
            'filters' => ['nullable', 'array'],
        ];
    }
}
