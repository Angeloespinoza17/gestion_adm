<?php

namespace App\Http\Requests\Convivencia;

use App\Models\Convivencia\ConvivenciaSociogram;
use App\Models\Convivencia\ConvivenciaSociogramQuestion;
use App\Models\CourseSection;
use App\Models\StudentEnrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveConvivenciaSociogramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $questionsRule = $this->isMethod('POST')
            ? ['required', 'array', 'min:1']
            : ['sometimes', 'nullable', 'array', 'min:1'];

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
            'questions' => $questionsRule,
            'questions.*.prompt' => ['required_with:questions', 'string', 'max:191'],
            'questions.*.selection_type' => ['required_with:questions', Rule::in(array_column(ConvivenciaSociogramQuestion::SELECTION_OPTIONS, 'value'))],
            'questions.*.max_choices' => ['nullable', 'integer', 'min:1', 'max:10'],
            'questions.*.active' => ['sometimes', 'boolean'],
            'answers' => ['nullable', 'array'],
            'answers.*.question_order' => ['required_with:answers', 'integer', 'min:1'],
            'answers.*.respondent_student_id' => ['required_with:answers', 'integer', 'exists:student_profiles,id'],
            'answers.*.selected_student_id' => ['required_with:answers', 'integer', 'exists:student_profiles,id'],
            'answers.*.selection_type' => ['required_with:answers', Rule::in(array_column(ConvivenciaSociogramQuestion::SELECTION_OPTIONS, 'value'))],
            'answers.*.notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $answers = $this->input('answers');
            if (! is_array($answers) || $answers === []) {
                return;
            }

            $sociogram = $this->route('sociogram');
            $courseSectionId = (int) ($this->input('course_section_id') ?: $sociogram?->course_section_id);
            $academicYearId = (int) ($this->input('academic_year_id') ?: $sociogram?->academic_year_id);
            if (! $academicYearId && $courseSectionId) {
                $academicYearId = (int) CourseSection::query()->whereKey($courseSectionId)->value('academic_year_id');
            }

            $allowedStudents = StudentEnrollment::query()
                ->where('course_section_id', $courseSectionId)
                ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
                ->whereNotIn('enrollment_status', StudentEnrollment::NON_ROSTER_STATUS_VALUES)
                ->pluck('student_profile_id')
                ->mapWithKeys(fn ($id) => [(int) $id => true])
                ->all();
            $questions = $this->input('questions');
            if (! is_array($questions)) {
                $questions = $sociogram?->questions()
                    ->orderBy('id')
                    ->get(['selection_type', 'max_choices'])
                    ->map(fn ($question) => $question->only(['selection_type', 'max_choices']))
                    ->all() ?? [];
            }

            $duplicates = [];
            $choicesPerRespondent = [];
            foreach (array_values($answers) as $index => $answer) {
                if (! is_array($answer)) {
                    continue;
                }
                $respondentId = (int) ($answer['respondent_student_id'] ?? 0);
                $selectedId = (int) ($answer['selected_student_id'] ?? 0);
                $questionOrder = (int) ($answer['question_order'] ?? 0);
                $question = $questions[$questionOrder - 1] ?? null;

                if (! isset($allowedStudents[$respondentId])) {
                    $validator->errors()->add("answers.{$index}.respondent_student_id", 'Quien responde debe pertenecer al curso seleccionado.');
                }
                if (! isset($allowedStudents[$selectedId])) {
                    $validator->errors()->add("answers.{$index}.selected_student_id", 'La estudiante seleccionada debe pertenecer al curso indicado.');
                }
                if ($respondentId > 0 && $respondentId === $selectedId) {
                    $validator->errors()->add("answers.{$index}.selected_student_id", 'Una estudiante no puede seleccionarse a sí misma.');
                }
                if (! is_array($question)) {
                    $validator->errors()->add("answers.{$index}.question_order", 'La pregunta seleccionada no existe en este sociograma.');

                    continue;
                }
                if (($answer['selection_type'] ?? null) !== ($question['selection_type'] ?? null)) {
                    $validator->errors()->add("answers.{$index}.selection_type", 'El tipo de respuesta debe coincidir con el tipo de la pregunta.');
                }

                $duplicateKey = "{$questionOrder}:{$respondentId}:{$selectedId}";
                if (isset($duplicates[$duplicateKey])) {
                    $validator->errors()->add("answers.{$index}.selected_student_id", 'Esta selección ya fue registrada para la misma pregunta.');
                }
                $duplicates[$duplicateKey] = true;

                $respondentKey = "{$questionOrder}:{$respondentId}";
                $choicesPerRespondent[$respondentKey] = ($choicesPerRespondent[$respondentKey] ?? 0) + 1;
                if ($choicesPerRespondent[$respondentKey] > (int) ($question['max_choices'] ?? 3)) {
                    $validator->errors()->add("answers.{$index}.selected_student_id", 'Se superó el máximo de elecciones permitido para esta pregunta.');
                }
            }
        });
    }
}
