<?php

namespace App\Http\Requests\Attendance;

use App\Models\Attendance\AttendanceCase;
use App\Services\Attendance\AttendanceManagementAccessService;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceCaseCauseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $case = $this->route('attendanceCase');
        $access = app(AttendanceManagementAccessService::class);

        return $case instanceof AttendanceCase && $access->canManageCases($this->user()) && $access->canViewCase($this->user(), $case);
    }

    public function rules(): array
    {
        return [
            'absence_reason_id' => ['required', 'integer', 'exists:attendance_absence_reasons,id'],
            'is_primary' => ['required', 'boolean'],
            'information_source' => ['required', 'string', 'max:80'],
            'identified_on' => ['required', 'date'],
            'observations' => ['nullable', 'string', 'max:3000'],
            'is_sensitive' => ['required', 'boolean'],
        ];
    }
}
