<?php

namespace App\Http\Requests\Pme;

use App\Services\Pme\PmeCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePmeGoalMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pme_objective_id' => ['required', 'integer', 'exists:pme_objetivos,id'],
            'goal_label' => ['required', 'string', 'max:191'],
            'baseline_value' => ['nullable', 'numeric'],
            'expected_result' => ['nullable', 'numeric'],
            'current_result' => ['nullable', 'numeric'],
            'information_source' => ['nullable', 'string', 'max:191'],
            'measured_at' => ['required', 'date'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'analysis' => ['nullable', 'string'],
            'state' => ['required', Rule::in(PmeCatalogService::GOAL_STATES)],
        ];
    }
}
