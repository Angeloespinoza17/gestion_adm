<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceFollowupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action_type' => ['required', 'string', 'max:60'],
            'action_date' => ['required', 'date'],
            'status' => ['nullable', Rule::in(['pending', 'completed', 'cancelled'])],
            'notes' => ['required', 'string', 'max:5000'],
            'next_action_date' => ['nullable', 'date', 'after_or_equal:action_date'],
        ];
    }
}
