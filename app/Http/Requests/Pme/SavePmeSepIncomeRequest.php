<?php

namespace App\Http\Requests\Pme;

use App\Services\Pme\PmeCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePmeSepIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pme_plan_id' => ['nullable', 'integer', 'exists:pme_planes,id'],
            'school_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'income_type' => ['required', Rule::in(PmeCatalogService::INCOME_TYPES)],
            'estimated_amount' => ['nullable', 'numeric', 'min:0'],
            'received_amount' => ['required', 'numeric', 'min:0'],
            'received_at' => ['nullable', 'date'],
            'bank_account' => ['nullable', 'string', 'max:191'],
            'state' => ['required', Rule::in(PmeCatalogService::INCOME_STATES)],
            'observations' => ['nullable', 'string'],
            'document' => ['nullable', 'file', 'max:10240'],
        ];
    }
}
