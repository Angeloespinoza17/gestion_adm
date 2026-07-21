<?php

namespace App\Http\Requests\Students;

use Illuminate\Validation\Rule;

class StudentReportDetailRequest extends StudentReportRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:500'],
            'detail_search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['name', 'course'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
