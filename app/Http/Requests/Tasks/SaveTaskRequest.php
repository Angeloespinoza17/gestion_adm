<?php

namespace App\Http\Requests\Tasks;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', Rule::in(Task::priorityValues())],
            'status' => ['required', Rule::in(Task::statusValues())],
            'stakeholder' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'owner_user_id' => [
                'required',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query
                        ->where('active', true)
                        ->where(function ($inner) {
                            $inner->where('user_type', 'staff')->orWhereNotNull('staff_id');
                        });
                }),
            ],
            'parent_task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'auto_complete_parent_on_subtasks_done' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'El nombre de la tarea es obligatorio.',
            'priority.required' => 'La prioridad es obligatoria.',
            'status.required' => 'El estado es obligatorio.',
            'owner_user_id.required' => 'El funcionario responsable es obligatorio.',
            'owner_user_id.exists' => 'El funcionario responsable debe ser un usuario activo de personal.',
            'due_date.date' => 'La fecha de corte debe ser una fecha válida.',
        ];
    }
}
