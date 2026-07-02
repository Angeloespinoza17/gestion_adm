<?php

namespace App\Http\Requests\Convivencia;

use App\Models\Convivencia\ConvivenciaIdpsResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveConvivenciaIdpsResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_id' => ['required', 'integer', 'exists:convivencia_idps_periods,id'],
            'dimension_id' => ['required', 'integer', 'exists:convivencia_idps_dimensions,id'],
            'instrument_id' => ['nullable', 'integer', 'exists:convivencia_idps_instruments,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'related_plan_id' => ['nullable', 'integer', 'exists:convivencia_plans,id'],
            'result_scope' => ['required', Rule::in(array_column(ConvivenciaIdpsResult::SCOPE_OPTIONS, 'value'))],
            'reference_label' => ['nullable', 'string', 'max:191'],
            'score' => ['nullable', 'numeric'],
            'percentage' => ['nullable', 'numeric', 'between:0,100'],
            'sample_size' => ['nullable', 'integer', 'min:0'],
            'qualitative_observations' => ['nullable', 'string'],
            'improvement_actions' => ['nullable', 'string'],
            'is_sensitive' => ['sometimes', 'boolean'],
        ];
    }
}
