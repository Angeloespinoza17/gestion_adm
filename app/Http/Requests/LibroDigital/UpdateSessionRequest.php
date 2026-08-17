<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.sessions.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'scheduled_date' => ['sometimes', 'date_format:Y-m-d'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
            'school_day_block_id' => ['sometimes', 'nullable', 'integer', 'exists:school_day_blocks,id'],
            'teacher_staff_id' => ['sometimes', 'integer', 'exists:staff,id'],
            'room_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'modality' => ['sometimes', 'nullable', 'string', 'max:50'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
