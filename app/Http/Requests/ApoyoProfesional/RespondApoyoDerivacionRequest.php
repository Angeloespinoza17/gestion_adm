<?php

namespace App\Http\Requests\ApoyoProfesional;

use App\Models\ApoyoProfesional\ApoyoDerivacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RespondApoyoDerivacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_column(ApoyoDerivacion::STATUS_OPTIONS, 'value'))],
            'destination_response' => ['nullable', 'string'],
        ];
    }
}
