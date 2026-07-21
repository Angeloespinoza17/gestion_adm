<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class PreviewAttendanceImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_section_id' => ['required', 'integer', 'exists:course_sections,id'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:'.config('attendance.max_upload_kb', 25600)],
        ];
    }
}
