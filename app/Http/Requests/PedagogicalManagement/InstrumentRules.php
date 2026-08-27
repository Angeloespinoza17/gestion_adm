<?php

namespace App\Http\Requests\PedagogicalManagement;

use App\Enums\PedagogicalManagement\EvaluationPurpose;
use App\Enums\PedagogicalManagement\InstrumentType;
use App\Enums\PedagogicalManagement\WorkModality;
use Illuminate\Validation\Rule;

trait InstrumentRules
{
    /** @return array<string,mixed> */
    protected function instrumentRules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'school_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:lcd_schools,id'],
            'academic_year_id' => [$required, 'integer', 'exists:academic_years,id'],
            'owner_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'subject_id' => [$required, 'integer', 'exists:schedule_subjects,id'],
            'unit_id' => ['sometimes', 'nullable', 'integer', 'exists:lcd_curriculum_units,id'],
            'title' => [$required, 'string', 'min:3', 'max:191'],
            'grade_label' => ['sometimes', 'nullable', 'string', 'max:80'],
            'instrument_type' => [$required, Rule::enum(InstrumentType::class)],
            'evaluation_purpose' => [$required, Rule::enum(EvaluationPurpose::class)],
            'work_modality' => [$required, Rule::enum(WorkModality::class)],
            'application_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'between:1,600'],
            'declared_total_points' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:999999.99'],
            'passing_percentage' => ['sometimes', 'nullable', 'numeric', 'between:0,100'],
            'weighting_percentage' => ['sometimes', 'nullable', 'numeric', 'between:0,100'],
            'minimum_grade' => ['sometimes', 'nullable', 'numeric', 'between:1,7'],
            'maximum_grade' => ['sometimes', 'nullable', 'numeric', 'between:1,7', 'gt:minimum_grade'],
            'accessibility_measures' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'course_ids' => [$partial ? 'sometimes' : 'required', 'array', 'min:1', 'max:30'],
            'course_ids.*' => ['integer', 'distinct', 'exists:course_sections,id'],
            'learning_objective_ids' => ['sometimes', 'array', 'max:50'],
            'learning_objective_ids.*' => ['integer', 'distinct', 'exists:lcd_learning_objectives,id'],
        ];
    }
}
