<?php

namespace App\Http\Requests\Pme;

use App\Services\Pme\PmeCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePmeIndicatorMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'measured_at' => ['required', 'date'],
            'measured_value' => ['required', 'numeric'],
            'information_source' => ['nullable', 'string', 'max:191'],
            'analysis' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'state' => ['nullable', Rule::in(PmeCatalogService::INDICATOR_STATES)],
        ];
    }
}
