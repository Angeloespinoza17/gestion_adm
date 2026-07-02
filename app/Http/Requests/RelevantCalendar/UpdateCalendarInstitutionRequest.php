<?php

namespace App\Http\Requests\RelevantCalendar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCalendarInstitutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $institutionId = $this->route('calendarInstitution')?->id;

        return [
            'name' => ['required', 'string', 'max:191', Rule::unique('calendar_institutions', 'name')->ignore($institutionId)],
            'description' => ['nullable', 'string'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
