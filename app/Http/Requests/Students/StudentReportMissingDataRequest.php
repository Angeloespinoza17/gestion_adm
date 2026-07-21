<?php

namespace App\Http\Requests\Students;

use App\Services\Students\StudentReportService;
use Illuminate\Validation\Rule;

class StudentReportMissingDataRequest extends StudentReportRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'dimension' => ['required', 'string', Rule::in(StudentReportService::MISSING_DATA_DIMENSIONS)],
        ];
    }
}
