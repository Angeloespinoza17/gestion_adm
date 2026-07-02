<?php

namespace App\Http\Requests\Pme;

use App\Services\Pme\PmeCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePmeReflectiveMonitoringRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pme_plan_id' => ['required', 'integer', 'exists:pme_planes,id'],
            'pme_dimension_id' => ['nullable', 'integer', 'exists:pme_dimensiones,id'],
            'pme_objective_id' => ['nullable', 'integer', 'exists:pme_objetivos,id'],
            'pme_strategy_id' => ['nullable', 'integer', 'exists:pme_estrategias,id'],
            'pme_action_id' => ['nullable', 'integer', 'exists:pme_acciones,id'],
            'monitored_at' => ['required', 'date'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'guiding_questions' => ['nullable', 'array'],
            'guiding_questions.*' => ['string'],
            'observed_progress' => ['nullable', 'string'],
            'difficulties' => ['nullable', 'string'],
            'reviewed_evidences' => ['nullable', 'string'],
            'decisions_taken' => ['nullable', 'string'],
            'required_adjustments' => ['nullable', 'string'],
            'next_steps' => ['nullable', 'string'],
            'state' => ['required', Rule::in(PmeCatalogService::MONITORING_STATES)],
            'observations' => ['nullable', 'string'],
        ];
    }
}
