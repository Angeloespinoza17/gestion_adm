<?php

namespace App\Http\Requests\Orientation;

use App\Models\Orientation\OrientationPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveOrientationPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('orientation.manage_plan');
    }

    public function rules(): array
    {
        $plan = $this->route('plan');

        return [
            'year' => [
                'required',
                'integer',
                'between:2020,2100',
                Rule::unique('orientation_plans', 'year')->ignore($plan instanceof OrientationPlan ? $plan->id : null),
            ],
            'title' => ['required', 'string', 'max:191'],
            'general_objective' => ['nullable', 'string', 'max:5000'],
            'description' => ['nullable', 'string', 'max:20000'],
            'status' => ['required', Rule::in(OrientationPlan::STATUSES)],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'year.unique' => 'Ya existe un Plan de Orientación para este año.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $plan = $this->route('plan');
            if (
                $plan instanceof OrientationPlan
                && (int) $this->input('year') !== (int) $plan->year
                && $plan->actions()->exists()
            ) {
                $validator->errors()->add('year', 'El año no puede cambiar cuando el plan ya tiene acciones.');
            }
        });
    }
}
