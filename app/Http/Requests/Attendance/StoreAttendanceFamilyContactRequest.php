<?php

namespace App\Http\Requests\Attendance;

use App\Models\Attendance\AttendanceCase;
use App\Services\Attendance\AttendanceManagementAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceFamilyContactRequest extends FormRequest
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
            'contacted_at' => ['required', 'date'],
            'channel' => ['required', Rule::in(['phone', 'whatsapp', 'email', 'meeting', 'home_visit', 'other'])],
            'contacted_person' => ['nullable', 'string', 'max:255'],
            'result' => ['required', Rule::in(['successful', 'no_answer', 'wrong_number', 'message_sent', 'meeting_scheduled', 'guardian_reports_cause', 'other'])],
            'observation' => ['nullable', 'string', 'max:3000'],
            'next_contact_at' => ['nullable', 'date', 'after:contacted_at'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
