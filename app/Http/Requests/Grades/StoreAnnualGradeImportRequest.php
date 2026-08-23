<?php

namespace App\Http\Requests\Grades;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnnualGradeImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['sometimes', 'nullable', 'integer', 'exists:lcd_schools,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'file' => ['required', 'file', 'mimes:xls,xlsx', 'max:'.config('grades.max_upload_kb', 25600)],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Selecciona el reporte anual de calificaciones en formato .xls o .xlsx.',
            'file.max' => 'El Excel no puede superar 25 MB.',
        ];
    }
}
