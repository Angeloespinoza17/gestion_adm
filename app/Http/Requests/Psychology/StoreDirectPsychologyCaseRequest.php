<?php

namespace App\Http\Requests\Psychology;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDirectPsychologyCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_profile_id' => [
                'required',
                'integer',
                Rule::exists('student_profiles', 'id')->where('general_status', 'activo'),
            ],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['sometimes', Rule::in(['open', 'assessment', 'active_intervention'])],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'confidentiality' => ['required', Rule::in(['private_psychology', 'psychology_team', 'interdisciplinary_team'])],
            'general_reason' => ['required', 'string', 'max:191'],
            'categories' => ['nullable', 'string', 'max:3000'],
            'objectives' => ['nullable', 'string', 'max:6000'],
            'next_action' => ['nullable', 'string', 'max:2000'],
            'next_review_on' => ['nullable', 'date', 'after_or_equal:today'],
            'guardian_information_status' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_profile_id.required' => 'Debes seleccionar una estudiante.',
            'student_profile_id.exists' => 'La estudiante seleccionada no está activa o ya no está disponible.',
        ];
    }
}
