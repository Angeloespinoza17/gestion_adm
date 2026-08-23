<?php

namespace App\Http\Requests\Attendance;

use App\Services\Attendance\AttendanceManagementAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAttendanceExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(AttendanceManagementAccessService::class)->canExport($this->user());
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'report_type' => ['required', Rule::in([
                'executive', 'students', 'courses', 'risk', 'alerts', 'interventions', 'goals', 'financial', 'data_quality',
                'institutional_management', 'course_management', 'individual', 'family_interview', 'critical_cases', 'intervention_effectiveness',
            ])],
            'format' => ['required', Rule::in(['pdf', 'xls', 'csv'])],
            'filters' => ['nullable', 'array'],
            'filters.course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'filters.student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
        ];
    }
}
