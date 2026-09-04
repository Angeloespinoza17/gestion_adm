<?php

namespace App\Http\Requests\Orientation;

use App\Models\Orientation\OrientationAction;
use App\Models\Orientation\OrientationPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveOrientationActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('orientation.manage_plan');
    }

    public function rules(): array
    {
        $planId = $this->plan()?->id;

        return [
            'title' => ['required', 'string', 'max:191'],
            'objective' => ['nullable', 'string', 'max:5000'],
            'description' => ['nullable', 'string', 'max:20000'],
            'target_levels' => ['nullable', 'string', 'max:3000'],
            'planned_verification_means' => ['nullable', 'string', 'max:5000'],
            'material_resources' => ['nullable', 'string', 'max:5000'],
            'responsible_summary' => ['nullable', 'string', 'max:3000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(OrientationAction::STATUSES)],
            'progress' => ['required', 'integer', 'between:0,100'],
            'sort_order' => ['nullable', 'integer', 'between:1,10000'],
            'responsible_user_ids' => ['nullable', 'array', 'max:50'],
            'responsible_user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'related_plan_ids' => ['nullable', 'array', 'max:50'],
            'related_plan_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('orientation_related_plans', 'id')->where(fn ($query) => $query->where('orientation_plan_id', $planId)),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $plan = $this->plan();
            if (! $plan) {
                return;
            }

            foreach (['start_date', 'end_date'] as $field) {
                $value = $this->input($field);
                if ($value && (int) date('Y', strtotime((string) $value)) !== (int) $plan->year) {
                    $validator->errors()->add($field, "La fecha debe pertenecer al año {$plan->year} del plan.");
                }
            }
        });
    }

    private function plan(): ?OrientationPlan
    {
        $plan = $this->route('plan');
        if ($plan instanceof OrientationPlan) {
            return $plan;
        }

        $action = $this->route('action');

        return $action instanceof OrientationAction ? $action->plan : null;
    }
}
