<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BibliotecaReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::in(['daily', 'weekly', 'monthly', 'semestral', 'annual'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
