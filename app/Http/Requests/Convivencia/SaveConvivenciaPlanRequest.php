<?php

namespace App\Http\Requests\Convivencia;

use App\Models\Convivencia\ConvivenciaPlan;
use App\Models\Convivencia\ConvivenciaPlanAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveConvivenciaPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'responsible_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'name' => ['required', 'string', 'max:191'],
            'general_objective' => ['required', 'string'],
            'specific_objectives' => ['nullable', 'array'],
            'specific_objectives.*' => ['string'],
            'resources_required' => ['nullable', 'string'],
            'indicators_summary' => ['nullable', 'string'],
            'verification_means_summary' => ['nullable', 'string'],
            'status' => ['required', Rule::in(array_column(ConvivenciaPlan::STATUS_OPTIONS, 'value'))],
            'advance_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'observations' => ['nullable', 'string'],
            'final_evaluation' => ['nullable', 'string'],
            'is_sensitive' => ['sometimes', 'boolean'],
            'actions' => ['nullable', 'array'],
            'actions.*.dimension_item_id' => ['nullable', 'integer', 'exists:convivencia_catalog_items,id'],
            'actions.*.responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'actions.*.responsible_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'actions.*.responsible_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'actions.*.action_type' => ['required_with:actions', Rule::in(array_column(ConvivenciaPlanAction::TYPE_OPTIONS, 'value'))],
            'actions.*.title' => ['required_with:actions', 'string', 'max:191'],
            'actions.*.description' => ['nullable', 'string'],
            'actions.*.dimension_label' => ['nullable', 'string', 'max:160'],
            'actions.*.responsible_label' => ['nullable', 'string', 'max:160'],
            'actions.*.starts_on' => ['nullable', 'date'],
            'actions.*.ends_on' => ['nullable', 'date', 'after_or_equal:actions.*.starts_on'],
            'actions.*.required_resources' => ['nullable', 'string'],
            'actions.*.indicator_summary' => ['nullable', 'string'],
            'actions.*.verification_means' => ['nullable', 'string'],
            'actions.*.status' => ['nullable', 'string', 'max:50'],
            'actions.*.advance_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'actions.*.observations' => ['nullable', 'string'],
            'actions.*.evidence_summary' => ['nullable', 'string'],
        ];
    }
}
