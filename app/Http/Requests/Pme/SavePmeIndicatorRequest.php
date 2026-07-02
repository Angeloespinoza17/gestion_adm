<?php

namespace App\Http\Requests\Pme;

use App\Services\Pme\PmeCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePmeIndicatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pme_objective_id' => ['required', 'integer', 'exists:pme_objetivos,id'],
            'pme_strategy_id' => ['nullable', 'integer', 'exists:pme_estrategias,id'],
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'indicator_type' => ['required', Rule::in(PmeCatalogService::INDICATOR_TYPES)],
            'baseline_value' => ['nullable', 'numeric'],
            'target_value' => ['nullable', 'numeric'],
            'current_value' => ['nullable', 'numeric'],
            'measurement_unit' => ['nullable', 'string', 'max:80'],
            'verification_source' => ['nullable', 'string', 'max:191'],
            'measurement_frequency' => ['required', Rule::in(PmeCatalogService::INDICATOR_FREQUENCIES)],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'state' => ['required', Rule::in(PmeCatalogService::INDICATOR_STATES)],
            'observations' => ['nullable', 'string'],
        ];
    }
}
