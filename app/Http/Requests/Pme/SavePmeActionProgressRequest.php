<?php

namespace App\Http\Requests\Pme;

use App\Services\Pme\PmeCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePmeActionProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'progress_percentage' => ['required', 'numeric', 'between:0,100'],
            'executed_budget' => ['nullable', 'numeric', 'min:0'],
            'state' => ['nullable', Rule::in(PmeCatalogService::ACTION_STATES)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
