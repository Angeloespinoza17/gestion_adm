<?php

namespace App\Http\Requests\Pme;

use App\Services\Pme\PmeCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePmeActivityRequest extends FormRequest
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
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'scheduled_date' => ['nullable', 'date'],
            'completed_date' => ['nullable', 'date', 'after_or_equal:scheduled_date'],
            'state' => ['required', Rule::in(PmeCatalogService::ACTIVITY_STATES)],
            'observations' => ['nullable', 'string'],
        ];
    }
}
