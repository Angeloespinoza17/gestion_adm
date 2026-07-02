<?php

namespace App\Http\Requests\Porter;

use App\Models\PorterDailyLogEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePorterDailyLogEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shift_label' => ['nullable', 'string', 'max:80'],
            'category' => ['required', Rule::in(array_column(PorterDailyLogEntry::CATEGORY_OPTIONS, 'value'))],
            'priority' => ['required', Rule::in(array_column(PorterDailyLogEntry::PRIORITY_OPTIONS, 'value'))],
            'status' => ['nullable', Rule::in(array_column(PorterDailyLogEntry::STATUS_OPTIONS, 'value'))],
            'title' => ['required', 'string', 'max:191'],
            'detail' => ['required', 'string'],
        ];
    }
}
