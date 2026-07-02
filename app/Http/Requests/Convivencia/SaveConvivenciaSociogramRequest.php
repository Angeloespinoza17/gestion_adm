<?php

namespace App\Http\Requests\Convivencia;

use App\Models\Convivencia\ConvivenciaSociogram;
use App\Models\Convivencia\ConvivenciaSociogramQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveConvivenciaSociogramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['required', 'integer', 'exists:course_sections,id'],
            'title' => ['required', 'string', 'max:191'],
            'applied_on' => ['required', 'date'],
            'status' => ['required', Rule::in(array_column(ConvivenciaSociogram::STATUS_OPTIONS, 'value'))],
            'confidentiality_level' => ['required', 'string', 'max:50'],
            'matrix_summary' => ['nullable', 'array'],
            'result_summary' => ['nullable', 'array'],
            'interpretation' => ['nullable', 'string'],
            'is_sensitive' => ['sometimes', 'boolean'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.prompt' => ['required_with:questions', 'string', 'max:191'],
            'questions.*.selection_type' => ['required_with:questions', Rule::in(array_column(ConvivenciaSociogramQuestion::SELECTION_OPTIONS, 'value'))],
            'questions.*.max_choices' => ['nullable', 'integer', 'min:1', 'max:10'],
            'questions.*.active' => ['sometimes', 'boolean'],
            'answers' => ['nullable', 'array'],
            'answers.*.question_order' => ['required_with:answers', 'integer', 'min:1'],
            'answers.*.respondent_student_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'answers.*.selected_student_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'answers.*.selection_type' => ['required_with:answers', Rule::in(array_column(ConvivenciaSociogramQuestion::SELECTION_OPTIONS, 'value'))],
            'answers.*.notes' => ['nullable', 'string'],
        ];
    }
}
