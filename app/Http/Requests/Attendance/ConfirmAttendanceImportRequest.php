<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmAttendanceImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'conflict_strategy' => ['required', Rule::in(['reject', 'overwrite', 'skip'])],
            'student_matches' => ['nullable', 'array'],
            'student_matches.*.row' => ['required', 'integer', 'min:1'],
            'student_matches.*.student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
        ];
    }
}
