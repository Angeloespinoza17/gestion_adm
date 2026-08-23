<?php

namespace App\Http\Requests\Attendance;

use App\Models\Attendance\AttendanceCase;
use App\Services\Attendance\AttendanceManagementAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceActionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $case = $this->route('attendanceCase');
        $access = app(AttendanceManagementAccessService::class);

        return $case instanceof AttendanceCase && $access->canManagePlans($this->user()) && $access->canViewCase($this->user(), $case);
    }

    public function rules(): array
    {
        return [
            'initial_situation' => ['required', 'string', 'max:5000'],
            'objective' => ['required', 'string', 'max:3000'],
            'goal_type' => ['required', Rule::in(['attendance_rate', 'unjustified_absences', 'absence_streak'])],
            'goal_value' => ['required', 'numeric', 'min:0', 'max:100'],
            'goal_window_days' => ['required', 'integer', 'min:7', 'max:365'],
            'starts_on' => ['required', 'date'],
            'review_on' => ['required', 'date', 'after:starts_on'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'actions' => ['nullable', 'array', 'max:30'],
            'actions.*.title' => ['required_with:actions', 'string', 'max:160'],
            'actions.*.description' => ['nullable', 'string', 'max:2000'],
            'actions.*.responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'actions.*.frequency' => ['nullable', 'string', 'max:50'],
            'actions.*.due_at' => ['nullable', 'date'],
        ];
    }
}
