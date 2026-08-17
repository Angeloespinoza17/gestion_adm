<?php

namespace App\Http\Requests\Inspectoria;

use App\Models\Inspectoria\InspectoriaDailyLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInspectoriaDailyLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'happened_at' => ['required', 'date'],
            'category' => ['required', Rule::in(array_keys(InspectoriaDailyLog::CATEGORIES))],
            'priority' => ['required', Rule::in(['baja', 'media', 'alta', 'urgente'])],
            'status' => ['nullable', Rule::in(['registrado', 'en_seguimiento', 'cerrado'])],
            'title' => ['required', 'string', 'max:191'],
            'detail' => ['required', 'string', 'max:4000'],
            'requires_follow_up' => ['boolean'],
            'follow_up_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
