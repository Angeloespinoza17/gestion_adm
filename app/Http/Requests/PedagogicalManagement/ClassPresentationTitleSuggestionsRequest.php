<?php

namespace App\Http\Requests\PedagogicalManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassPresentationTitleSuggestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('class-presentations.create') === true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:lcd_schools,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'course_id' => ['required', 'integer', 'exists:course_sections,id'],
            'subject_id' => ['required', 'integer', 'exists:schedule_subjects,id'],
            'unit_id' => ['required', 'integer', 'exists:lcd_curriculum_units,id'],
            'learning_objective_ids' => ['required', 'array', 'min:1', 'max:10'],
            'learning_objective_ids.*' => ['integer', 'distinct', 'exists:lcd_learning_objectives,id'],
            'class_type' => ['required', Rule::in(array_keys(config('class_presentations.options.class_type')))],
        ];
    }
}
