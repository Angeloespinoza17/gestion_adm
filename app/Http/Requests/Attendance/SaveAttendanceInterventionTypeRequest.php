<?php

namespace App\Http\Requests\Attendance;

use App\Services\Attendance\AttendanceManagementAccessService;
use Illuminate\Foundation\Http\FormRequest;

class SaveAttendanceInterventionTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(AttendanceManagementAccessService::class)->canConfigure($this->user());
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:80', 'alpha_dash', 'unique:attendance_intervention_types,code,'.($this->route('attendanceInterventionType')?->id ?? 'NULL')],
            'name' => ['required', 'string', 'max:160'],
            'category' => ['nullable', 'string', 'max:80'],
            'family_contact' => ['required', 'boolean'],
            'sensitive' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:1000'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
