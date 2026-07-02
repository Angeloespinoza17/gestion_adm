<?php

namespace App\Http\Requests\Library;

use App\Models\Library\BibliotecaUsoEspacio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBibliotecaUsoEspacioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'biblioteca_espacio_id' => ['required', 'integer', 'exists:biblioteca_espacios,id'],
            'activity_type' => ['required', Rule::in(BibliotecaUsoEspacio::ACTIVITY_TYPES)],
            'title' => ['required', 'string', 'max:191'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'responsible_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'requested_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'attendee_count' => ['nullable', 'integer', 'min:1'],
            'requested_resources' => ['nullable', 'array'],
            'requested_resources.*' => ['string', 'max:120'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'status' => ['nullable', Rule::in(BibliotecaUsoEspacio::STATUS_OPTIONS)],
            'observations' => ['nullable', 'string'],
            'evidence' => ['nullable', 'array'],
            'evidence.*' => ['string', 'max:2048'],
        ];
    }
}
