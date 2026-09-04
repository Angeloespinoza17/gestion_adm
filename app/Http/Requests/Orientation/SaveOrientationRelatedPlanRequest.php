<?php

namespace App\Http\Requests\Orientation;

use App\Models\Orientation\OrientationPlan;
use App\Models\Orientation\OrientationRelatedPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOrientationRelatedPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('orientation.manage_plan');
    }

    public function rules(): array
    {
        $relatedPlan = $this->route('relatedPlan');
        $plan = $this->route('plan');
        $planId = $plan instanceof OrientationPlan
            ? $plan->id
            : ($relatedPlan instanceof OrientationRelatedPlan ? $relatedPlan->orientation_plan_id : null);

        return [
            'name' => [
                'required',
                'string',
                'max:191',
                Rule::unique('orientation_related_plans', 'name')
                    ->where(fn ($query) => $query->where('orientation_plan_id', $planId))
                    ->ignore($relatedPlan instanceof OrientationRelatedPlan ? $relatedPlan->id : null),
            ],
            'category' => ['required', Rule::in(['institutional', 'regulatory', 'external', 'other'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'reference_url' => ['nullable', 'url:http,https', 'max:2048'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
