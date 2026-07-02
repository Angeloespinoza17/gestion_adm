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
        return [
            'protocol_id' => ['required', 'integer', 'exists:convivencia_protocols,id'],
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
            if (!$this->filled('case_id') && !$this->filled('complaint_id')) {
                $validator->errors()->add('case_id', 'Debes asociar la activación a un caso o una denuncia.');
            }
        });
    }
}
