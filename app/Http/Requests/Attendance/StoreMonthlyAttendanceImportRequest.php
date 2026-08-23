<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class StoreMonthlyAttendanceImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'file' => ['required', 'file', 'mimes:xls,xlsx', 'max:25600'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Selecciona el reporte mensual en formato .xls o .xlsx.',
            'file.max' => 'El Excel no puede superar 25 MB.',
        ];
    }
}
