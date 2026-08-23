<?php

namespace App\Http\Requests\Attendance;

use App\Models\Attendance\AttendanceCase;
use App\Services\Attendance\AttendanceManagementAccessService;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceMeetingAgreementRequest extends FormRequest
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
            'meeting_date' => ['required', 'date'],
            'agreement' => ['required', 'string', 'max:3000'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'commitment_date' => ['nullable', 'date', 'after_or_equal:meeting_date'],
        ];
    }
}
