<?php

namespace App\Http\Requests\Attendance;

use App\Models\Attendance\AttendanceCase;
use App\Services\Attendance\AttendanceManagementAccessService;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceCaseNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $case = $this->route('attendanceCase');
        $access = app(AttendanceManagementAccessService::class);

        return $case instanceof AttendanceCase
            && $access->canManageCases($this->user())
            && $access->canViewCase($this->user(), $case)
            && (! $this->boolean('is_sensitive') || $access->canViewSensitive($this->user()));
    }

    public function rules(): array
    {
        return ['note' => ['required', 'string', 'max:5000'], 'is_sensitive' => ['required', 'boolean']];
    }
}
