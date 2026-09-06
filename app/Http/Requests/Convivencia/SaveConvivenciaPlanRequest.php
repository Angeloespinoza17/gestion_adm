<?php

namespace App\Http\Requests\Convivencia;

use App\Models\Convivencia\ConvivenciaPlan;
use App\Models\Convivencia\ConvivenciaPlanAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveConvivenciaPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $planId = $this->route('plan')?->id;

        return [
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'calendar_year' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                'integer',
                'between:2020,2100',
                Rule::unique('convivencia_plans', 'calendar_year')->ignore($planId),
            ],
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
            'revision' => [$this->isMethod('put') || $this->isMethod('patch') ? 'required' : 'sometimes', 'integer', 'min:1'],
            'change_summary' => ['nullable', 'string', 'max:500'],
            'source_document_name' => ['nullable', 'string', 'max:255'],
            'source_document_sha256' => ['nullable', 'string', 'size:64'],
            'institutional_protocol' => ['nullable', 'array'],
            'institutional_protocol.*.title' => ['required_with:institutional_protocol', 'string', 'max:191'],
            'institutional_protocol.*.description' => ['nullable', 'string'],
            'institutional_protocol.*.responsible' => ['nullable', 'string', 'max:191'],
            'institutional_protocol.*.timing' => ['nullable', 'string', 'max:191'],
            'institutional_protocol.*.requirements' => ['nullable', 'string'],
            'evaluation_indicators' => ['nullable', 'array'],
            'evaluation_indicators.*.code' => ['nullable', 'string', 'max:60'],
            'evaluation_indicators.*.title' => ['required_with:evaluation_indicators', 'string', 'max:191'],
            'evaluation_indicators.*.description' => ['nullable', 'string'],
            'evaluation_indicators.*.target_value' => ['nullable', 'numeric'],
            'evaluation_indicators.*.target_unit' => ['nullable', 'string', 'max:60'],
            'evaluation_indicators.*.frequency' => ['nullable', 'string', 'max:100'],
            'evaluation_indicators.*.verification_source' => ['nullable', 'string', 'max:500'],
            'regulatory_linkage_text' => ['nullable', 'string'],
            'regulatory_review_required' => ['sometimes', 'boolean'],
            'is_sensitive' => ['sometimes', 'boolean'],
            'actions' => ['nullable', 'array'],
            'actions.*.id' => ['nullable', 'integer'],
            'actions.*.dimension_item_id' => ['nullable', 'integer', 'exists:convivencia_catalog_items,id'],
            'actions.*.responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'actions.*.responsible_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'actions.*.responsible_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'actions.*.action_type' => ['required_with:actions', Rule::in(array_column(ConvivenciaPlanAction::TYPE_OPTIONS, 'value'))],
            'actions.*.title' => ['required_with:actions', 'string', 'max:191'],
            'actions.*.objective' => ['nullable', 'string'],
            'actions.*.description' => ['nullable', 'string'],
            'actions.*.target_audience' => ['nullable', 'string'],
            'actions.*.planned_month' => ['nullable', 'integer', 'between:1,12'],
            'actions.*.date_precision' => ['nullable', Rule::in(['month', 'exact'])],
            'actions.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'actions.*.weight_percent' => ['nullable', 'numeric', 'between:0,100'],
            'actions.*.dimension_label' => ['nullable', 'string', 'max:160'],
            'actions.*.responsible_label' => ['nullable', 'string', 'max:160'],
            'actions.*.starts_on' => ['nullable', 'date'],
            'actions.*.ends_on' => ['nullable', 'date', 'after_or_equal:actions.*.starts_on'],
            'actions.*.required_resources' => ['nullable', 'string'],
            'actions.*.indicator_summary' => ['nullable', 'string'],
            'actions.*.verification_means' => ['nullable', 'string'],
            'actions.*.status' => ['nullable', Rule::in(array_column(ConvivenciaPlanAction::STATUS_OPTIONS, 'value'))],
            'actions.*.advance_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'actions.*.observations' => ['nullable', 'string'],
            'actions.*.evidence_summary' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'calendar_year.unique' => 'Ya existe un Plan de Gestión de la Convivencia para ese año.',
            'revision.required' => 'La versión abierta está desactualizada. Recarga el plan antes de guardar.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var ConvivenciaPlan|null $plan */
            $plan = $this->route('plan');
            if ($plan && $this->has('calendar_year') && (int) $this->input('calendar_year') !== (int) $plan->calendar_year) {
                $validator->errors()->add('calendar_year', 'El año identifica al plan y no puede cambiarse. Archiva el plan incorrecto y crea uno nuevo.');
            }

            $requiresReview = $this->has('regulatory_review_required')
                ? $this->boolean('regulatory_review_required')
                : (bool) ($plan?->regulatory_review_required ?? false);
            if ($requiresReview && in_array($this->input('status'), ['vigente', 'en_ejecucion', 'finalizado'], true)) {
                $validator->errors()->add('status', 'Debes cerrar la revisión de la cláusula RICE antes de publicar o ejecutar el plan.');
            }
        });
    }
}
