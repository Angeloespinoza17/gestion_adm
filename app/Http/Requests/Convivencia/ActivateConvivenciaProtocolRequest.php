<?php

namespace App\Http\Requests\Convivencia;

use App\Models\Convivencia\ConvivenciaProtocolActivation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivateConvivenciaProtocolRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $activation = $this->route('activation');

        if ($activation instanceof ConvivenciaProtocolActivation) {
            $this->merge([
                'protocol_id' => $this->input('protocol_id', $activation->protocol_id),
                'case_id' => $this->input('case_id', $activation->case_id),
                'complaint_id' => $this->input('complaint_id', $activation->complaint_id),
                'current_step_id' => $this->input('current_step_id', $activation->current_step_id),
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $revisionRules = $this->route('activation')
            ? ['required', 'integer', 'min:1']
            : ['nullable', 'integer', 'min:1'];

        return [
            'protocol_id' => ['required', 'integer', 'exists:convivencia_protocols,id'],
            'revision' => $revisionRules,
            'case_id' => ['nullable', 'integer', 'exists:convivencia_cases,id'],
            'complaint_id' => ['nullable', 'integer', 'exists:convivencia_complaints,id'],
            'current_step_id' => ['nullable', 'integer', 'exists:convivencia_protocol_steps,id'],
            'status' => ['nullable', Rule::in(array_column(ConvivenciaProtocolActivation::STATUS_OPTIONS, 'value'))],
            'current_stage_name' => ['nullable', 'string', 'max:160'],
            'due_at' => ['nullable', 'date'],
            'involved_snapshot' => ['nullable', 'array'],
            'actions_taken' => ['nullable', 'string'],
            'measures_adopted' => ['nullable', 'string'],
            'closing_summary' => ['nullable', 'string'],
            'action_type' => ['nullable', 'string', 'max:80'],
            'log_notes' => ['nullable', 'string'],
            'completed_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hasCase = $this->filled('case_id');
            $hasComplaint = $this->filled('complaint_id');
            if ($hasCase === $hasComplaint) {
                $message = $hasCase
                    ? 'Asocia la activación solo a un caso o solo a una denuncia, no a ambos.'
                    : 'Debes asociar la activación a un caso o una denuncia.';
                $validator->errors()->add('case_id', $message);
                $validator->errors()->add('complaint_id', $message);
            }
        });
    }
}
