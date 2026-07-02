<?php

namespace App\Http\Requests\RelevantCalendar;

use Illuminate\Foundation\Http\FormRequest;

class StoreCalendarInstitutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191', 'unique:calendar_institutions,name'],
            'description' => ['nullable', 'string'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
