<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.reports.export') ?? false;
    }

    public function rules(): array
    {
        $curriculum = in_array($this->input('report_type'), ['curriculum_objectives', 'curriculum_program'], true);
        $program = $this->input('report_type') === 'curriculum_program';
        $filterRules = ['sometimes', 'array'];
        if ($curriculum) {
            $filterRules[] = $program
                ? 'array:program_id,unit_id'
                : 'array:level_code,grade_code,curriculum_track,subject_code,subject,schedule_subject_id,objective_type,axis_code,status,source,query,catalog_id,course_section_id';
        }

        return [
            'school_id' => [$curriculum ? 'required' : 'sometimes', 'nullable', 'integer', 'exists:lcd_schools,id'],
            'academic_year_id' => [$curriculum ? 'required' : 'sometimes', 'nullable', 'integer', 'exists:academic_years,id'],
            'book_id' => ['sometimes', 'nullable', 'string', 'max:40', 'regex:/^(?:[0-9]+|[0-9A-HJKMNP-TV-Z]{26})$/i'],
            'report_type' => ['required', Rule::in([
                'official_roster', 'enrollment_movements', 'attendance', 'session_attendance', 'daily_attendance',
                'monthly_attendance', 'student_absences', 'lesson_records', 'curriculum_coverage', 'assessments',
                'coexistence', 'pie', 'early_withdrawals', 'prolonged_absences', 'parvularia_book',
                'amendment_history', 'audit', 'closure_status', 'executive', 'curriculum_objectives', 'curriculum_program',
            ])],
            'format' => ['required', Rule::in($program ? ['pdf'] : ($curriculum ? ['pdf', 'xlsx'] : ['pdf', 'xlsx', 'csv', 'json']))],
            'filters' => $filterRules,
            'filters.program_id' => [$program ? 'required' : 'sometimes', 'nullable', 'string', 'max:40', 'regex:/^(?:[0-9]+|[0-9A-HJKMNP-TV-Z]{26})$/i'],
            'filters.unit_id' => ['sometimes', 'nullable', 'string', 'max:40', 'regex:/^(?:[0-9]+|[0-9A-HJKMNP-TV-Z]{26})$/i'],
            'filters.level_code' => ['sometimes', 'nullable', 'string', 'max:60'],
            'filters.grade_code' => ['sometimes', 'nullable', 'string', 'max:60'],
            'filters.curriculum_track' => ['sometimes', 'nullable', 'string', 'max:30'],
            'filters.subject_code' => ['sometimes', 'nullable', 'string', 'max:100'],
            'filters.subject' => ['sometimes', 'nullable', 'string', 'max:160'],
            'filters.schedule_subject_id' => ['sometimes', 'nullable', 'integer', 'exists:schedule_subjects,id'],
            'filters.objective_type' => ['sometimes', 'nullable', 'string', 'max:20'],
            'filters.axis_code' => ['sometimes', 'nullable', 'string', 'max:80'],
            'filters.status' => ['sometimes', 'nullable', Rule::in(['all', 'active', 'inactive'])],
            'filters.source' => ['sometimes', 'nullable', 'string', 'max:180'],
            'filters.query' => ['sometimes', 'nullable', 'string', 'max:180'],
            'filters.catalog_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'filters.course_section_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'period' => ['sometimes', 'nullable', 'string', 'max:40'],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'teacher_staff_id' => ['sometimes', 'nullable', 'integer', 'exists:staff,id'],
            'signature_status' => ['sometimes', 'nullable', 'string', 'max:40'],
            'normative_profile_id' => ['sometimes', 'nullable', 'integer', 'exists:lcd_regulatory_profiles,id'],
        ];
    }
}
