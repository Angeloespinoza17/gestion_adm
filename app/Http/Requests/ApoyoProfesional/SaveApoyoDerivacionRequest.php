<?php

namespace App\Http\Requests\ApoyoProfesional;

use App\Models\ApoyoProfesional\ApoyoDerivacion;
use App\Models\ApoyoProfesional\ApoyoProfesionalProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveApoyoDerivacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attention_id' => ['required', 'integer', 'exists:apoyo_atenciones,id'],
            'destination_professional_id' => ['nullable', 'integer', 'exists:apoyo_profesionales,id'],
            'destination_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'origin_area_slug' => ['nullable', 'string', 'max:80'],
            'origin_area_name' => ['nullable', 'string', 'max:120'],
            'destination_area_slug' => ['required', Rule::in(array_column(ApoyoProfesionalProfile::AREA_OPTIONS, 'value'))],
            'destination_area_name' => ['required', 'string', 'max:120'],
            'urgency_level' => ['required', Rule::in(array_column(ApoyoDerivacion::URGENCY_OPTIONS, 'value'))],
            'confidentiality_level' => ['nullable', Rule::in(['general', 'reservada', 'confidencial', 'alta_confidencialidad'])],
            'status' => ['nullable', Rule::in(array_column(ApoyoDerivacion::STATUS_OPTIONS, 'value'))],
            'reason' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'derived_at' => ['required', 'date'],
        ];
    }
}
