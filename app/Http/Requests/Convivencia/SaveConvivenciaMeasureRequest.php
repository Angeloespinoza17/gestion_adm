<?php

namespace App\Http\Requests\Convivencia;

use App\Models\Convivencia\ConvivenciaMeasure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveConvivenciaMeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'case_id' => ['nullable', 'integer', 'exists:convivencia_cases,id'],
            'student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'measure_type_item_id' => ['nullable', 'integer', 'exists:convivencia_catalog_items,id'],
            'responsible_user_id' => ['required', 'integer', 'exists:users,id'],
            'responsible_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'validated_by' => ['nullable', 'integer', 'exists:users,id'],
            'measure_type_label' => ['nullable', 'string', 'max:160'],
            'description' => ['required', 'string'],
            'training_objective' => ['required', 'string'],
            'assigned_at' => ['required', 'date'],
            'due_at' => ['required', 'date', 'after_or_equal:assigned_at'],
            'status' => ['required', Rule::in(array_column(ConvivenciaMeasure::STATUS_OPTIONS, 'value'))],
            'evidence_summary' => ['nullable', 'string'],
            'student_reflection' => ['nullable', 'string'],
            'repair_action' => ['nullable', 'string'],
            'responsible_notes' => ['nullable', 'string'],
            'closure_notes' => ['nullable', 'string'],
            'closed_at' => ['nullable', 'date'],
            'is_sensitive' => ['sometimes', 'boolean'],
        ];
    }
}
