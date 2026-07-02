<?php

namespace App\Http\Requests\RelevantCalendar;

use Illuminate\Foundation\Http\FormRequest;

class StoreCalendarProcessTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191', 'unique:calendar_process_types,name'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
