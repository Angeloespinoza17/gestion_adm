<?php

namespace App\Http\Requests\Pme;

use App\Services\Pme\PmeCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePmeActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pme_plan_id' => ['required', 'integer', 'exists:pme_planes,id'],
            'pme_dimension_id' => ['required', 'integer', 'exists:pme_dimensiones,id'],
            'pme_objective_id' => ['required', 'integer', 'exists:pme_objetivos,id'],
            'pme_strategy_id' => ['required', 'integer', 'exists:pme_estrategias,id'],
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'justification' => ['nullable', 'string'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'responsible_area' => ['nullable', 'string', 'max:120'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'planned_budget' => ['nullable', 'numeric', 'min:0'],
            'committed_budget' => ['nullable', 'numeric', 'min:0'],
            'executed_budget' => ['nullable', 'numeric', 'min:0'],
            'funding_source' => ['required', Rule::in(PmeCatalogService::ACTION_FUNDING_SOURCES)],
            'cost_center_reference' => ['nullable', 'string', 'max:120'],
            'external_accounting_reference' => ['nullable', 'string', 'max:120'],
            'document_reference' => ['nullable', 'string', 'max:120'],
            'minimum_evidence_required' => ['nullable', 'integer', 'min:0'],
            'progress_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'state' => ['required', Rule::in(PmeCatalogService::ACTION_STATES)],
            'observations' => ['nullable', 'string'],
            'indicator_ids' => ['nullable', 'array'],
            'indicator_ids.*' => ['integer', 'exists:pme_indicadores,id'],
        ];
    }
}
