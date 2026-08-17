<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;

class ReconcileAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.closures.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'month' => ['required', 'integer', 'between:1,12'],
            'evidence_reference' => ['required', 'string', 'min:8', 'max:191'],
            'rows' => ['required', 'array'],
            'rows.*.student_reference' => ['required', 'string', 'max:191'],
            'groups' => ['required', 'array'],
            'groups.*.teaching_group_public_id' => ['required', 'string', 'max:26'],
            'groups.*.expected_total' => ['required', 'integer', 'min:0'],
            'groups.*.present_total' => ['required', 'integer', 'min:0'],
            'groups.*.absent_total' => ['required', 'integer', 'min:0'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
