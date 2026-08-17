<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;

class StoreSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.sessions.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'scheduled_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'school_day_block_id' => ['nullable', 'integer', 'exists:school_day_blocks,id'],
            'teacher_staff_id' => ['required', 'integer', 'exists:staff,id'],
            'schedule_subject_id' => ['nullable', 'integer', 'exists:schedule_subjects,id'],
            'room_name' => ['nullable', 'string', 'max:100'],
            'modality' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
