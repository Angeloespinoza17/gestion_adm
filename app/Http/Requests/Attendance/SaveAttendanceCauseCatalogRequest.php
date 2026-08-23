<?php

namespace App\Http\Requests\Attendance;

use App\Services\Attendance\AttendanceManagementAccessService;
use Illuminate\Foundation\Http\FormRequest;

class SaveAttendanceCauseCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(AttendanceManagementAccessService::class)->canManageCauses($this->user());
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:60', 'alpha_dash', 'unique:attendance_absence_reasons,code,'.($this->route('attendanceAbsenceReason')?->id ?? 'NULL')],
            'name' => ['required', 'string', 'max:160'],
            'category' => ['nullable', 'string', 'max:80'],
            'is_sensitive' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:1000'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
