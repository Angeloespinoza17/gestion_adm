<?php

namespace App\Http\Requests\Pme;

use App\Services\Pme\PmeCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePmeObjectiveRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'strategic_goal' => ['nullable', 'string'],
            'global_indicator' => ['nullable', 'string', 'max:191'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'state' => ['required', Rule::in(PmeCatalogService::OBJECTIVE_STATES)],
            'observations' => ['nullable', 'string'],
        ];
    }
}
