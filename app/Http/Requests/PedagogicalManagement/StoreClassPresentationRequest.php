<?php

namespace App\Http\Requests\PedagogicalManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreClassPresentationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $normalized = [
            'presentation_provider' => $this->input('presentation_provider', 'powerpoint'),
            'generate_teacher_guide' => $this->input('generate_teacher_guide', true),
        ];
        foreach (array_keys((array) config('class_presentations.multiple_options', [])) as $key) {
            $value = $this->input($key);
            if (is_string($value) || is_numeric($value)) {
                $normalized[$key] = [$value];
            }
        }
        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('class-presentations.create') === true;
    }

    public function rules(): array
    {
        $option = fn (string $key): array => ['required', Rule::in(array_keys((array) config("class_presentations.options.{$key}", [])))];
        $multiple = fn (string $key): array => [
            'required', 'array',
            'min:'.(int) config("class_presentations.multiple_options.{$key}.min", 1),
            'max:'.(int) config("class_presentations.multiple_options.{$key}.max", 3),
        ];
        $multipleItem = fn (string $key): array => [
            'required', 'string', 'distinct', Rule::in(array_keys((array) config("class_presentations.options.{$key}", []))),
        ];
        $maxFiles = (int) config('class_presentations.storage.max_reference_files', 5);
        $maxFileKb = (int) config('class_presentations.storage.max_reference_file_kb', 15360);

        return [
            'school_id' => ['required', 'integer', 'exists:lcd_schools,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'course_id' => ['required', 'integer', 'exists:course_sections,id'],
            'subject_id' => ['required', 'integer', 'exists:schedule_subjects,id'],
            'unit_id' => ['required', 'integer', 'exists:lcd_curriculum_units,id'],
            'learning_objective_ids' => ['required', 'array', 'min:1', 'max:10'],
            'learning_objective_ids.*' => ['required', 'integer', 'distinct', 'exists:lcd_learning_objectives,id'],
            'title' => ['required', 'string', 'max:191'],
            'presentation_provider' => ['required', 'string', Rule::in(['powerpoint', 'canva'])],
            'canva_template_id' => ['nullable', 'required_if:presentation_provider,canva', 'string', 'regex:/^[A-Za-z0-9_-]{1,191}$/'],
            'canva_template_title' => ['nullable', 'required_if:presentation_provider,canva', 'string', 'max:191'],
            'class_type' => $option('class_type'),
            'duration_minutes' => ['required', 'integer', Rule::in(config('class_presentations.options.duration_minutes'))],
            'slide_count' => ['required', 'integer', Rule::in(config('class_presentations.options.slide_count'))],
            'prior_knowledge' => $option('prior_knowledge'), 'depth' => $option('depth'),
            'methodology' => $multiple('methodology'), 'methodology.*' => $multipleItem('methodology'),
            'tone' => $option('tone'), 'opening' => $option('opening'),
            'activity' => $multiple('activity'), 'activity.*' => $multipleItem('activity'),
            'assessment' => $multiple('assessment'), 'assessment.*' => $multipleItem('assessment'),
            'aspect_ratio' => $option('aspect_ratio'), 'visual_style' => $option('visual_style'),
            'palette' => $option('palette'),
            'visual_resources' => $multiple('visual_resources'), 'visual_resources.*' => $multipleItem('visual_resources'),
            'speaker_notes' => ['required', 'boolean'], 'bibliography' => ['required', 'boolean'],
            'web_research' => ['required', 'boolean'], 'generate_pdf' => ['required', 'boolean'],
            'generate_teacher_guide' => ['required', 'accepted'],
            'generate_activity' => ['required', 'boolean'], 'generate_assessment' => ['required', 'boolean'],
            'include_cover' => ['required', 'boolean'], 'include_objectives' => ['required', 'boolean'],
            'include_synthesis' => ['required', 'boolean'], 'include_closure' => ['required', 'boolean'],
            'reference_files' => ['sometimes', 'array', 'max:'.$maxFiles],
            'reference_files.*' => ['file', 'max:'.$maxFileKb, 'mimes:pdf,docx,pptx,txt,md'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) config('class_presentations.multiple_options', []) as $key => $rules) {
                $selected = array_values(array_filter((array) $this->input($key, []), fn (mixed $value): bool => trim((string) $value) !== ''));
                $exclusive = array_values((array) ($rules['exclusive'] ?? []));
                if (count($selected) > 1 && array_intersect($selected, $exclusive) !== []) {
                    $validator->errors()->add($key, 'La opción automática o "sin" no se puede combinar con otras alternativas.');
                }
            }

            if ($this->input('presentation_provider') === 'canva' && ! (bool) config('canva.enabled')) {
                $validator->errors()->add('presentation_provider', 'La integración Canva no está habilitada en el servidor.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'reference_files.*.mimes' => 'Los materiales deben ser PDF, DOCX, PPTX, TXT o Markdown.',
            'learning_objective_ids.min' => 'Selecciona al menos un objetivo de aprendizaje.',
            'methodology.min' => 'Selecciona al menos una estrategia metodológica.',
            'activity.min' => 'Selecciona al menos una alternativa de actividad.',
            'assessment.min' => 'Selecciona al menos una alternativa de evaluación.',
            'visual_resources.min' => 'Selecciona al menos un recurso visual.',
            'methodology.max' => 'Puedes combinar hasta tres estrategias metodológicas.',
            'activity.max' => 'Puedes combinar hasta tres actividades.',
            'assessment.max' => 'Puedes combinar hasta tres evaluaciones.',
            'visual_resources.max' => 'Puedes combinar hasta cuatro recursos visuales.',
            'canva_template_id.required_if' => 'Selecciona una plantilla Canva compatible.',
            'canva_template_title.required_if' => 'La plantilla Canva seleccionada no tiene un título válido.',
            'generate_teacher_guide.accepted' => 'La guía docente PDF es obligatoria para esta generación.',
        ];
    }
}
