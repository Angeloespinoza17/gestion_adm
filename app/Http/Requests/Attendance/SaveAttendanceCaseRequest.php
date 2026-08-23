<?php

namespace App\Http\Requests\Attendance;

use App\Models\Attendance\AttendanceCase;
use App\Services\Attendance\AttendanceManagementAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAttendanceCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $access = app(AttendanceManagementAccessService::class);
        $case = $this->route('attendanceCase');
        if ($case instanceof AttendanceCase) {
            return $access->canManageCases($this->user()) && $access->canViewCase($this->user(), $case);
        }

        return $access->canManageCases($this->user())
            && $access->canViewStudent($this->user(), (int) $this->input('student_profile_id'), (int) $this->input('academic_year_id'));
    }

    public function rules(): array
    {
        $creating = ! $this->route('attendanceCase');

        return [
            'student_profile_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:student_profiles,id'],
            'academic_year_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'opened_from_alert_id' => ['nullable', 'integer', 'exists:attendance_alerts,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'reference_adult_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'reference_adult_name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(AttendanceCase::STATUSES)],
            'priority' => ['nullable', Rule::in(['preventive', 'medium', 'high', 'critical'])],
            'initial_situation' => [$creating ? 'required' : 'nullable', 'string', 'max:5000'],
            'next_review_on' => ['nullable', 'date'],
            'closure_reason' => ['nullable', Rule::in(['goal_met', 'sustained_improvement', 'student_withdrawal', 'school_change', 'other']), 'required_if:status,closed'],
            'closure_notes' => ['nullable', 'string', 'max:3000'],
            'participant_user_ids' => ['nullable', 'array', 'max:30'],
            'participant_user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
