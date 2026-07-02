<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTaskAssignerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $staffUserRule = Rule::exists('users', 'id')->where(function ($query) {
            $query
                ->where('active', true)
                ->where(function ($inner) {
                    $inner->where('user_type', 'staff')->orWhereNotNull('staff_id');
                });
        });

        return [
            'target_user_id' => ['required', $staffUserRule],
            'assigner_user_id' => ['required', 'different:target_user_id', Rule::exists('users', 'id')->where('active', true)],
            'active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'target_user_id.required' => 'Selecciona el funcionario receptor de tareas.',
            'target_user_id.exists' => 'El receptor debe ser un usuario activo de personal.',
            'assigner_user_id.required' => 'Selecciona el usuario asignador.',
            'assigner_user_id.different' => 'El asignador debe ser distinto del funcionario receptor.',
            'assigner_user_id.exists' => 'El asignador debe ser un usuario activo.',
        ];
    }
}
