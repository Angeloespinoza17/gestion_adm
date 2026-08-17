<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;

class CloseMonthlyAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.closures.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'month' => ['required', 'integer', 'between:1,12'],
            'teaching_group_id' => ['sometimes', 'nullable', 'integer', 'exists:lcd_teaching_groups,id'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
