<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceStatisticsFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'period' => ['nullable', Rule::in([
                'today', 'yesterday', 'current_week', 'previous_week', 'last_7_school_days',
                'last_14_school_days', 'last_30_days', 'current_month', 'previous_month',
                'quarter', 'semester', 'academic_year', 'custom',
            ])],
            'from' => ['nullable', 'date', 'required_if:period,custom'],
            'to' => ['nullable', 'date', 'after_or_equal:from', 'required_if:period,custom'],
            'education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'school_day_template_id' => ['nullable', 'integer', 'exists:school_day_templates,id'],
            'student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'enrollment_status' => ['nullable', 'string', 'max:40'],
            'attendance_status' => ['nullable', Rule::in(['present', 'absent'])],
            'absence_reason_id' => ['nullable', 'integer', 'exists:attendance_absence_reasons,id'],
            'is_justified' => ['nullable', 'boolean'],
            'gender' => ['nullable', 'string', 'max:50'],
            'commune' => ['nullable', 'string', 'max:100'],
            'is_pie_participant' => ['nullable', 'boolean'],
            'attendance_min' => ['nullable', 'numeric', 'between:0,100'],
            'attendance_max' => ['nullable', 'numeric', 'between:0,100'],
            'risk' => ['nullable', 'string', 'max:60'],
            'search' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', Rule::in(['student_name', 'course_name', 'attendance_rate', 'absent', 'late', 'early_departure'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('attendance_min') && $this->filled('attendance_max') && (float) $this->input('attendance_max') < (float) $this->input('attendance_min')) {
                $validator->errors()->add('attendance_max', 'La asistencia máxima debe ser mayor o igual que la mínima.');
            }
        });
    }
}
