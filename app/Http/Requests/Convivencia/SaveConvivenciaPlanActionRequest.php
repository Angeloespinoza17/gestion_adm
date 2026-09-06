<?php

namespace App\Http\Requests\Convivencia;

use App\Models\Convivencia\ConvivenciaPlanAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveConvivenciaPlanActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_revision' => ['required', 'integer', 'min:1'],
            'dimension_item_id' => ['nullable', 'integer', 'exists:convivencia_catalog_items,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'responsible_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'responsible_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'action_type' => ['required', Rule::in(array_column(ConvivenciaPlanAction::TYPE_OPTIONS, 'value'))],
            'title' => ['required', 'string', 'max:191'],
            'objective' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'target_audience' => ['nullable', 'string'],
            'planned_month' => ['nullable', 'required_if:date_precision,month', 'integer', 'between:1,12'],
            'date_precision' => ['required', Rule::in(['month', 'exact'])],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'weight_percent' => ['nullable', 'numeric', 'between:0,100'],
            'dimension_label' => ['nullable', 'string', 'max:160'],
            'responsible_label' => ['nullable', 'string', 'max:160'],
            'starts_on' => ['nullable', 'required_if:date_precision,exact', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'required_resources' => ['nullable', 'string'],
            'indicator_summary' => ['nullable', 'string'],
            'verification_means' => ['nullable', 'string'],
            'status' => ['required', Rule::in(array_column(ConvivenciaPlanAction::STATUS_OPTIONS, 'value'))],
            'observations' => ['nullable', 'string'],
            'evidence_summary' => ['nullable', 'string'],
            'change_summary' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'plan_revision.required' => 'Recarga el plan antes de modificar sus acciones.',
            'weight_percent.between' => 'La ponderación de la acción debe estar entre 0% y 100%.',
        ];
    }
}
