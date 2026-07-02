<?php

namespace App\Http\Requests\Library;

use App\Models\Library\BibliotecaPrestamo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBibliotecaPrestamoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'loan_code' => ['nullable', 'string', 'max:80'],
            'batch_code' => ['nullable', 'string', 'max:80'],
            'borrower_type' => ['required', Rule::in(BibliotecaPrestamo::BORROWER_TYPES)],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'biblioteca_ejemplar_id' => ['required', 'integer', 'exists:biblioteca_ejemplares,id'],
            'borrowed_at' => ['required', 'date'],
            'due_at' => ['required', 'date', 'after_or_equal:borrowed_at'],
            'delivered_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'reservation_id' => ['nullable', 'integer', 'exists:biblioteca_reservas,id'],
        ];
    }
}
