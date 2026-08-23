<?php

namespace App\Http\Requests\Infirmary;

use App\Models\Infirmary\InfirmaryDailyLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInfirmaryDailyLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'happened_at' => ['required', 'date'],
            'category' => ['required', Rule::in(array_keys(InfirmaryDailyLog::CATEGORIES))],
            'priority' => ['required', Rule::in(array_keys(InfirmaryDailyLog::PRIORITIES))],
            'status' => ['required', Rule::in(array_keys(InfirmaryDailyLog::STATUSES))],
            'title' => ['required', 'string', 'max:191'],
            'detail' => ['required', 'string', 'max:4000'],
            'action_taken' => ['nullable', 'string', 'max:2000'],
            'requires_follow_up' => ['required', 'boolean'],
            'follow_up_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'happened_at.required' => 'Indica la fecha y hora del hecho.',
            'category.in' => 'Selecciona una categoría propia de Enfermería.',
            'priority.in' => 'La prioridad seleccionada no es válida.',
            'status.in' => 'El estado seleccionado no es válido.',
            'title.required' => 'Ingresa un título breve para el registro.',
            'detail.required' => 'Describe el hecho relevante de la jornada.',
            'detail.max' => 'La descripción no puede superar los 4000 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'requires_follow_up' => $this->boolean('requires_follow_up'),
            'status' => $this->input('status', 'registrado'),
            'priority' => $this->input('priority', 'media'),
        ]);
    }
}
