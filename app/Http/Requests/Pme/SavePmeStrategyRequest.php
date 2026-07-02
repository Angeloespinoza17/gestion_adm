<?php

namespace App\Http\Requests\Pme;

use App\Services\Pme\PmeCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePmeStrategyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pme_objective_id' => ['required', 'integer', 'exists:pme_objetivos,id'],
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'execution_period' => ['nullable', 'string', 'max:120'],
            'state' => ['required', Rule::in(PmeCatalogService::STRATEGY_STATES)],
            'observations' => ['nullable', 'string'],
        ];
    }
}
