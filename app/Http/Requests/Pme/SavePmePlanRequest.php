<?php

namespace App\Http\Requests\Pme;

use App\Models\Pme\PmePlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePmePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'school_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'name' => ['required', 'string', 'max:191'],
            'period_label' => ['nullable', 'string', 'max:120'],
            'cycle_name' => ['nullable', 'string', 'max:120'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'state' => ['required', Rule::in(PmePlan::STATE_OPTIONS)],
            'general_description' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
        ];
    }
}
