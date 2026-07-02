<?php

namespace App\Http\Requests\Library;

use App\Models\Library\BibliotecaReserva;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBibliotecaReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reservation_code' => ['nullable', 'string', 'max:80'],
            'resource_type' => ['nullable', 'string', 'max:80'],
            'biblioteca_obra_id' => ['nullable', 'integer', 'exists:biblioteca_obras,id'],
            'biblioteca_ejemplar_id' => ['nullable', 'integer', 'exists:biblioteca_ejemplares,id'],
            'requester_type' => ['required', Rule::in(BibliotecaReserva::REQUESTER_TYPES)],
            'requested_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'requested_at' => ['nullable', 'date'],
            'pickup_at' => ['nullable', 'date'],
            'expected_return_at' => ['nullable', 'date', 'after_or_equal:pickup_at'],
            'purpose' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(BibliotecaReserva::STATUS_OPTIONS)],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
