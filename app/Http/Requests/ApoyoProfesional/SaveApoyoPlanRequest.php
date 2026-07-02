<?php

namespace App\Http\Requests\ApoyoProfesional;

use App\Models\ApoyoProfesional\ApoyoPlan;
use App\Models\ApoyoProfesional\ApoyoProfesionalProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveApoyoPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'responsible_professional_id' => ['nullable', 'integer', 'exists:apoyo_profesionales,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'area_slug' => ['nullable', Rule::in(array_column(ApoyoProfesionalProfile::AREA_OPTIONS, 'value'))],
            'area_name' => ['nullable', 'string', 'max:120'],
            'motive' => ['required', 'string', 'max:191'],
            'general_objective' => ['required', 'string'],
            'specific_objectives' => ['nullable', 'array'],
            'specific_objectives.*' => ['string'],
            'actions_summary' => ['nullable', 'string'],
            'responsibles_summary' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'indicators' => ['nullable', 'string'],
            'status' => ['required', Rule::in(array_column(ApoyoPlan::STATUS_OPTIONS, 'value'))],
            'evidences' => ['nullable', 'string'],
            'observations' => ['nullable', 'string'],
            'confidentiality_level' => ['nullable', Rule::in(['general', 'reservada', 'confidencial', 'alta_confidencialidad'])],
            'actions' => ['nullable', 'array'],
            'actions.*.action_description' => ['required_with:actions', 'string', 'max:191'],
            'actions.*.responsible_label' => ['nullable', 'string', 'max:160'],
            'actions.*.due_date' => ['nullable', 'date'],
            'actions.*.completed_at' => ['nullable', 'date'],
            'actions.*.status' => ['nullable', 'string', 'max:40'],
            'actions.*.observations' => ['nullable', 'string'],
        ];
    }
}
