<?php

namespace App\Http\Requests\Attendance;

use App\Models\Attendance\AttendanceCase;
use App\Services\Attendance\AttendanceManagementAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceCaseInterventionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $case = $this->route('attendanceCase');
        $access = app(AttendanceManagementAccessService::class);

        return $case instanceof AttendanceCase && $access->canManageInterventions($this->user()) && $access->canViewCase($this->user(), $case);
    }

    public function rules(): array
    {
        return [
            'intervention_type_id' => ['required', 'integer', 'exists:attendance_intervention_types,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'opened_at' => ['required', 'date'],
            'description' => ['required', 'string', 'max:5000'],
            'result' => ['nullable', 'string', 'max:80'],
            'result_summary' => ['nullable', 'string', 'max:3000'],
            'next_action_at' => ['nullable', 'date', 'after_or_equal:opened_at'],
            'status' => ['nullable', Rule::in(['new', 'pending_review', 'family_contact', 'intervention', 'follow_up', 'improved', 'no_improvement', 'referred', 'closed'])],
        ];
    }
}
