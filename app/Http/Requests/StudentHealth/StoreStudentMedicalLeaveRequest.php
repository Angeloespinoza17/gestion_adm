<?php

namespace App\Http\Requests\StudentHealth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStudentMedicalLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'reason' => ['required', 'string', 'max:1500'],
            'is_permanent' => ['required', 'boolean'],
            'source_module' => ['required', Rule::in(['infirmary', 'inspectoria'])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->boolean('is_permanent') && ! $this->filled('ends_on')) {
                    $validator->errors()->add('ends_on', 'La fecha de término es obligatoria cuando la licencia no es permanente.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'student_profile_id.required' => 'Selecciona una alumna del listado.',
            'student_profile_id.exists' => 'La alumna seleccionada no está disponible.',
            'starts_on.required' => 'Ingresa la fecha de inicio.',
            'ends_on.after_or_equal' => 'La fecha de término no puede ser anterior a la fecha de inicio.',
            'reason.required' => 'Ingresa el motivo de la licencia o certificado.',
            'reason.max' => 'El motivo no puede superar los 1500 caracteres.',
            'is_permanent.required' => 'Indica si corresponde a una condición permanente.',
            'source_module.in' => 'El origen del registro no es válido.',
        ];
    }
}
