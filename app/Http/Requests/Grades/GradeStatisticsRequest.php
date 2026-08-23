<?php

namespace App\Http\Requests\Grades;

use Illuminate\Foundation\Http\FormRequest;

class GradeStatisticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['sometimes', 'nullable', 'integer', 'exists:lcd_schools,id'],
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['sometimes', 'nullable', 'integer', 'exists:course_sections,id'],
            'schedule_subject_id' => ['sometimes', 'nullable', 'integer', 'exists:schedule_subjects,id'],
            'assessment_period_code' => ['sometimes', 'nullable', 'string', 'max:80', 'exists:lcd_assessment_periods,code'],
            // La importación anual no entrega fechas reales por evaluación.
            // El panel usa secuencia de evaluaciones y no acepta filtros cronológicos estimados.
        ];
    }
}
