<?php

namespace App\Http\Requests\Convivencia;

use App\Models\Convivencia\ConvivenciaProtocolActivationStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConvivenciaProtocolActivationStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'revision' => ['required', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(ConvivenciaProtocolActivationStep::STATUS_OPTIONS)],
            'notes' => ['nullable', 'string'],
            'outcome' => ['nullable', 'string'],
            'evidence_summary' => ['nullable', 'string'],
            'data' => ['nullable', 'array'],
            'completion_criteria' => ['nullable', 'array'],
            'started_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'extension_value' => ['nullable', 'integer', 'min:1', 'max:365'],
            'extension_unit' => ['nullable', Rule::in(['hours', 'calendar_days', 'business_days', 'school_days', 'external', 'external_defined'])],
            'extension_approved' => ['nullable', 'boolean'],
            'log_notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ((array) $this->input('completion_criteria', []) as $index => $criterion) {
                if (is_bool($criterion) || in_array($criterion, [0, 1, '0', '1'], true)) {
                    continue;
                }

                if (is_array($criterion)
                    && collect(['completed', 'confirmed', 'value'])
                        ->contains(fn (string $key) => array_key_exists($key, $criterion))) {
                    continue;
                }

                $validator->errors()->add(
                    "completion_criteria.{$index}",
                    'Cada criterio debe indicar un valor booleano o un objeto con completed, confirmed o value.'
                );
            }
        });
    }
}
