<?php

namespace App\Http\Requests\ApoyoProfesional;

use App\Models\ApoyoProfesional\ApoyoEntrevista;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveApoyoEntrevistaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'professional_id' => ['nullable', 'integer', 'exists:apoyo_profesionales,id'],
            'professional_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'interview_type' => ['required', Rule::in(array_column(ApoyoEntrevista::TYPE_OPTIONS, 'value'))],
            'interview_at' => ['required', 'date'],
            'participants' => ['nullable', 'array'],
            'participants.*' => ['string'],
            'motive' => ['required', 'string'],
            'topics' => ['nullable', 'string'],
            'agreements' => ['nullable', 'string'],
            'commitments' => ['nullable', 'string'],
            'follow_up_date' => ['nullable', 'date'],
            'status' => ['required', 'string', 'max:40'],
            'confidentiality_level' => ['required', Rule::in(['general', 'reservada', 'confidencial', 'alta_confidencialidad'])],
            'observations' => ['nullable', 'string'],
        ];
    }
}
