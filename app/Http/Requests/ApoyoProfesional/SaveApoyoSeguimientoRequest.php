<?php

namespace App\Http\Requests\ApoyoProfesional;

use App\Models\ApoyoProfesional\ApoyoSeguimiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveApoyoSeguimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attention_id' => ['required', 'integer', 'exists:apoyo_atenciones,id'],
            'student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'responsible_professional_id' => ['nullable', 'integer', 'exists:apoyo_profesionales,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'scheduled_at' => ['required', 'date'],
            'completed_at' => ['nullable', 'date'],
            'comment' => ['required', 'string'],
            'status' => ['required', Rule::in(array_column(ApoyoSeguimiento::STATUS_OPTIONS, 'value'))],
            'next_action' => ['nullable', 'string'],
            'evidence_summary' => ['nullable', 'string'],
            'result' => ['nullable', 'string'],
        ];
    }
}
