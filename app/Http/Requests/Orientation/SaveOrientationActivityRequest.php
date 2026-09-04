<?php

namespace App\Http\Requests\Orientation;

use App\Models\Orientation\OrientationActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveOrientationActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('orientation.manage_execution');
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:20000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in(OrientationActivity::STATUSES)],
            'contribution_percent' => ['required', 'integer', 'between:0,100'],
            'completion_percent' => ['required', 'integer', 'between:0,100'],
            'location' => ['nullable', 'string', 'max:191'],
            'participants' => ['nullable', 'string', 'max:5000'],
            'attendee_count' => ['nullable', 'integer', 'between:0,100000'],
            'results' => ['nullable', 'string', 'max:20000'],
        ];
    }

    public function messages(): array
    {
        return [
            'contribution_percent.required' => 'Indica cuánto aporta esta actividad al avance de la acción.',
            'contribution_percent.between' => 'El aporte de la actividad debe estar entre 0% y 100%.',
            'completion_percent.required' => 'Indica el porcentaje de cumplimiento de la actividad.',
            'completion_percent.between' => 'El cumplimiento de la actividad debe estar entre 0% y 100%.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $activity = $this->route('activity');
            $action = $this->route('action') ?: ($activity instanceof OrientationActivity ? $activity->action : null);
            $year = $action?->plan?->year;
            if (! $year) {
                return;
            }

            foreach (['starts_at', 'ends_at'] as $field) {
                $value = $this->input($field);
                if ($value && (int) date('Y', strtotime((string) $value)) !== (int) $year) {
                    $validator->errors()->add($field, "La fecha debe pertenecer al año {$year} del plan.");
                }
            }
        });
    }
}
