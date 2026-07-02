<?php

namespace App\Http\Requests\Pme;

use App\Services\Pme\PmeCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePmeMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pme_action_id' => ['required', 'integer', 'exists:pme_acciones,id'],
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'planned_date' => ['nullable', 'date'],
            'actual_completion_date' => ['nullable', 'date', 'after_or_equal:planned_date'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'progress_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'state' => ['required', Rule::in(PmeCatalogService::MILESTONE_STATES)],
            'observations' => ['nullable', 'string'],
        ];
    }
}
