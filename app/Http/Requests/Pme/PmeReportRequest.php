<?php

namespace App\Http\Requests\Pme;

use App\Services\Pme\PmeCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PmeReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'report_type' => ['required', Rule::in(PmeCatalogService::REPORT_TYPES)],
            'format' => ['nullable', Rule::in(['pantalla', 'pdf', 'excel'])],
            'pme_plan_id' => ['nullable', 'integer', 'exists:pme_planes,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'pme_dimension_id' => ['nullable', 'integer', 'exists:pme_dimensiones,id'],
            'pme_objective_id' => ['nullable', 'integer', 'exists:pme_objetivos,id'],
            'pme_strategy_id' => ['nullable', 'integer', 'exists:pme_estrategias,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'state' => ['nullable', 'string', 'max:80'],
            'funding_source' => ['nullable', 'string', 'max:80'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'evidence_type' => ['nullable', 'string', 'max:80'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
