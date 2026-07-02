<?php

namespace App\Http\Requests\RelevantCalendar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCalendarProcessTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $typeId = $this->route('calendarProcessType')?->id;

        return [
            'name' => ['required', 'string', 'max:191', Rule::unique('calendar_process_types', 'name')->ignore($typeId)],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
