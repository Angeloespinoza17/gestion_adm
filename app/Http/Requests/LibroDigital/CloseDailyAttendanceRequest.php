<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;

class CloseDailyAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.closures.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'teaching_group_id' => ['sometimes', 'nullable', 'integer', 'exists:lcd_teaching_groups,id'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
