<?php

namespace App\Http\Requests\Attendance;

use App\Models\Attendance\AttendanceActionPlan;
use App\Services\Attendance\AttendanceManagementAccessService;
use Illuminate\Foundation\Http\FormRequest;

class EvaluateAttendanceActionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('attendanceActionPlan');
        if (! $plan instanceof AttendanceActionPlan) {
            return false;
        }
        $plan->loadMissing('attendanceCase');
        $access = app(AttendanceManagementAccessService::class);

        return $access->canManagePlans($this->user()) && $access->canViewCase($this->user(), $plan->attendanceCase);
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:1000']];
    }
}
