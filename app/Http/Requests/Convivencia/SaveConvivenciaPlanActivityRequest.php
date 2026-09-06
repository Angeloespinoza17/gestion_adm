<?php

namespace App\Http\Requests\Convivencia;

use App\Models\Convivencia\ConvivenciaPlanActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveConvivenciaPlanActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currentTypeId = $this->route('activity')?->activity_type_item_id;

        return [
            'revision' => [$this->isMethod('put') || $this->isMethod('patch') ? 'required' : 'sometimes', 'integer', 'min:1'],
            'activity_type_item_id' => [
                'required',
                'integer',
                Rule::exists('convivencia_catalog_items', 'id')->where(function ($query) use ($currentTypeId): void {
                    $query->where('group', ConvivenciaPlanActivity::TYPE_GROUP)
                        ->where(function ($scope) use ($currentTypeId): void {
                            $scope->where('active', true);
                            if ($currentTypeId) {
                                $scope->orWhere('id', $currentTypeId);
                            }
                        });
                }),
            ],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in(array_column(ConvivenciaPlanActivity::STATUS_OPTIONS, 'value'))],
            'contribution_percent' => ['required', 'integer', 'between:0,100'],
            'completion_percent' => ['nullable', 'integer', 'between:0,100'],
            'location' => ['nullable', 'string', 'max:191'],
            'target_audience' => ['nullable', 'string'],
            'attendee_count' => ['nullable', 'integer', 'min:0'],
            'results' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'activity_type_item_id.required' => 'Selecciona el tipo de actividad que se realizará.',
            'activity_type_item_id.exists' => 'El tipo de actividad seleccionado no está disponible.',
        ];
    }
}
