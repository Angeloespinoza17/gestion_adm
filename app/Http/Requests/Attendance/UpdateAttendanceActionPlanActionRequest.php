<?php

namespace App\Http\Requests\Attendance;

use App\Models\Attendance\AttendanceActionPlanAction;
use App\Services\Attendance\AttendanceManagementAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceActionPlanActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $action = $this->route('attendanceActionPlanAction');
        if (! $action instanceof AttendanceActionPlanAction) {
            return false;
        }
        $action->loadMissing('plan.attendanceCase');
        $access = app(AttendanceManagementAccessService::class);

        return $access->canManagePlans($this->user()) && $access->canViewCase($this->user(), $action->plan->attendanceCase);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['pending', 'in_progress', 'completed', 'cancelled'])],
            'result' => ['nullable', 'string', 'max:3000', 'required_if:status,completed'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
