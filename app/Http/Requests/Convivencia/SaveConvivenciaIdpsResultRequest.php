<?php

namespace App\Http\Requests\Convivencia;

use App\Models\Convivencia\ConvivenciaIdpsResult;
use App\Models\Convivencia\ConvivenciaPlan;
use App\Services\Convivencia\ConvivenciaAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveConvivenciaIdpsResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var ConvivenciaIdpsResult|null $currentResult */
        $currentResult = $this->route('result');
        $currentDimensionId = $currentResult?->dimension_id;
        $currentInstrumentId = $currentResult?->instrument_id;

        return [
            'period_id' => ['required', 'integer', 'exists:convivencia_idps_periods,id'],
            'dimension_id' => [
                'required',
                'integer',
                Rule::exists('convivencia_idps_dimensions', 'id')
                    ->where(function ($query) use ($currentDimensionId) {
                        $query->where('active', true);
                        if ($currentDimensionId) {
                            $query->orWhere('id', $currentDimensionId);
                        }
                    }),
            ],
            'instrument_id' => [
                'nullable',
                'integer',
                Rule::exists('convivencia_idps_instruments', 'id')
                    ->where('dimension_id', $this->input('dimension_id'))
                    ->where(function ($query) use ($currentInstrumentId) {
                        $query->where('active', true);
                        if ($currentInstrumentId) {
                            $query->orWhere('id', $currentInstrumentId);
                        }
                    }),
            ],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'related_plan_id' => ['nullable', 'integer', 'exists:convivencia_plans,id'],
            'result_scope' => ['required', Rule::in(array_column(ConvivenciaIdpsResult::SCOPE_OPTIONS, 'value'))],
            'reference_label' => ['nullable', 'string', 'max:191'],
            'score' => ['nullable', 'numeric'],
            'percentage' => ['nullable', 'numeric', 'between:0,100'],
            'sample_size' => ['nullable', 'integer', 'min:0'],
            'qualitative_observations' => ['nullable', 'string'],
            'improvement_actions' => ['nullable', 'string'],
            'is_sensitive' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'dimension_id.exists' => 'La dimensión seleccionada está inactiva o no existe.',
            'instrument_id.exists' => 'El instrumento seleccionado está inactivo o no pertenece a la dimensión indicada.',
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('related_plan_id') || ! $this->filled('related_plan_id')) {
                return;
            }

            $plan = ConvivenciaPlan::query()->find($this->integer('related_plan_id'));
            $access = app(ConvivenciaAccessService::class);

            if (! $plan || ! $access->canViewPlan($this->user(), $plan)) {
                $validator->errors()->add(
                    'related_plan_id',
                    'El plan seleccionado no está disponible para tu nivel de acceso.',
                );
            }
        }];
    }
}
