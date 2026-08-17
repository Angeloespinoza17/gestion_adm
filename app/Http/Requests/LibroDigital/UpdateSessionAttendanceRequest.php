<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSessionAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.attendance.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'records' => ['required', 'array'],
            'records.*.id' => ['nullable', 'integer'],
            'records.*.roster_snapshot_item_id' => ['nullable', 'integer', 'exists:lcd_roster_snapshot_items,id'],
            'records.*.student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'records.*.student_enrollment_id' => ['nullable', 'integer', 'exists:student_enrollments,id'],
            // `pending` is a client-side placeholder. The controller omits those
            // rows so an autosave never manufactures an attendance decision.
            'records.*.status' => ['required', Rule::in(['pending', 'present', 'absent', 'late', 'left_early', 'not_applicable'])],
            'records.*.arrival_time' => ['nullable', 'date_format:H:i', 'required_if:records.*.status,late'],
            'records.*.departure_time' => ['nullable', 'date_format:H:i', 'required_if:records.*.status,left_early'],
            'records.*.justification' => ['nullable', 'string', 'max:255'],
            'records.*.observation' => ['nullable', 'string', 'max:500'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
