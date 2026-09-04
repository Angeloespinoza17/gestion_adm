<?php

namespace App\Http\Requests\Orientation;

use App\Models\Orientation\OrientationCalendarizationEntry;
use App\Models\Orientation\OrientationPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveOrientationCalendarizationEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('orientation.manage_plan');
    }

    public function rules(): array
    {
        $plan = $this->plan();

        return [
            'orientation_action_id' => [
                'nullable',
                'integer',
                Rule::exists('orientation_actions', 'id')
                    ->where(fn ($query) => $query->where('orientation_plan_id', $plan?->id)),
            ],
            'level_group' => ['required', Rule::in(OrientationCalendarizationEntry::LEVEL_GROUPS)],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::in(OrientationCalendarizationEntry::CATEGORIES)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(OrientationCalendarizationEntry::STATUSES)],
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

        $entry = $this->route('calendarizationEntry');

        return $entry instanceof OrientationCalendarizationEntry ? $entry->plan : null;
    }
}
