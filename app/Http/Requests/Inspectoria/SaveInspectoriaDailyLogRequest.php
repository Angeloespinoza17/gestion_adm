<?php

namespace App\Http\Requests\Inspectoria;

use App\Models\Inspectoria\InspectoriaDailyLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInspectoriaDailyLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'happened_at' => ['required', 'date'],
            'category' => ['required', Rule::in(array_keys(InspectoriaDailyLog::CATEGORIES))],
            'is_staff_lateness' => ['required', 'boolean'],
            'late_staff_id' => [
                Rule::requiredIf(fn () => $this->boolean('is_staff_lateness')),
                'nullable',
                'integer',
                Rule::exists('staff', 'id')->where('active', true),
            ],
            'associated_course_ids' => ['nullable', 'array', 'max:50'],
            'associated_course_ids.*' => ['integer', 'distinct', Rule::exists('course_sections', 'id')->where('active', true)],
            'lateness_minutes' => [
                Rule::requiredIf(fn () => $this->boolean('is_staff_lateness')),
                'nullable',
                'integer',
                Rule::in([5, 10, 15, 20, 30, 40]),
            ],
            'priority' => ['required', Rule::in(['baja', 'media', 'alta', 'urgente'])],
            'status' => ['nullable', Rule::in(['registrado', 'en_seguimiento', 'cerrado'])],
            'title' => ['required', 'string', 'max:191'],
            'detail' => ['required', 'string', 'max:4000'],
            'requires_follow_up' => ['boolean'],
            'follow_up_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'late_staff_id.required' => 'Selecciona el funcionario que registra el atraso.',
            'associated_course_ids.*.exists' => 'Uno de los cursos seleccionados no está disponible.',
            'lateness_minutes.required' => 'Selecciona los minutos de atraso.',
            'lateness_minutes.in' => 'Selecciona uno de los tiempos de atraso disponibles.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $isStaffLateness = $this->input('category') === 'asistencia' && $this->boolean('is_staff_lateness');

        $this->merge([
            'is_staff_lateness' => $isStaffLateness,
            'late_staff_id' => $isStaffLateness ? $this->input('late_staff_id') : null,
            'associated_course_ids' => $isStaffLateness ? (array) $this->input('associated_course_ids', []) : [],
            'lateness_minutes' => $isStaffLateness ? $this->input('lateness_minutes') : null,
            'student_profile_id' => $isStaffLateness ? null : $this->input('student_profile_id'),
            'course_section_id' => $isStaffLateness ? null : $this->input('course_section_id'),
        ]);
    }
}
