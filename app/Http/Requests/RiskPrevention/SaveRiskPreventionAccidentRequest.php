<?php

namespace App\Http\Requests\RiskPrevention;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRiskPreventionAccidentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'event_type' => $this->input('event_type', 'accidente'),
            'lost_days' => $this->input('lost_days', 0),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'occurred_at' => ['required', 'date'],
            'accident_type' => ['required', Rule::in(['student', 'staff', 'visit'])],
            'event_type' => ['required', Rule::in(['accidente', 'enfermedad_profesional'])],
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'involved_person_name' => ['required', 'string', 'max:160'],
            'involved_person_identifier' => ['nullable', 'string', 'max:120'],
            'location' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string'],
            'injuries' => ['nullable', 'string'],
            'injured_body_part' => ['nullable', 'string', 'max:120'],
            'lost_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'measures_taken' => ['nullable', 'string'],
            'referrals' => ['nullable', 'string'],
            'case_status' => ['required', Rule::in(['abierto', 'en_seguimiento', 'cerrado'])],
            'responsible_name' => ['nullable', 'string', 'max:160'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (
                $this->input('event_type') === 'enfermedad_profesional'
                && $this->input('accident_type') !== 'staff'
            ) {
                $validator->errors()->add(
                    'event_type',
                    'La enfermedad profesional sólo se puede registrar para funcionarios.',
                );
            }

            if (filled($this->input('staff_id')) && $this->input('accident_type') !== 'staff') {
                $validator->errors()->add(
                    'staff_id',
                    'El funcionario sólo puede asociarse a un caso de tipo Funcionario.',
                );
            }
        });
    }
}
