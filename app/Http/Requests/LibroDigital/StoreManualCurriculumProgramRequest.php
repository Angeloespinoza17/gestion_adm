<?php

namespace App\Http\Requests\LibroDigital;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreManualCurriculumProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('libro_digital.curriculum_programs.import') === true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['objective_ids', 'axes', 'units'] as $field) {
            $value = $this->input($field);
            if (! is_string($value)) {
                continue;
            }
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $this->merge([$field => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        $maxPdfKb = (int) config('libro_digital.curriculum_import.max_pdf_kb', 40960);

        return [
            'school_id' => ['nullable', 'integer'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'schedule_subject_id' => ['required', 'integer', 'exists:schedule_subjects,id'],
            'education_level_id' => ['required', 'integer', 'exists:education_levels,id'],
            'official_name' => ['required', 'string', 'max:190'],
            'official_code' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'issuing_authority' => ['required', 'string', 'max:180'],
            'decree' => ['nullable', 'string', 'max:160'],
            'edition' => ['nullable', 'string', 'max:100'],
            'publication_year' => ['nullable', 'integer', 'between:1900,2200'],
            'official_url' => ['nullable', 'url', 'max:2000'],
            'estimated_weeks' => ['nullable', 'integer', 'between:1,60'],
            'estimated_pedagogical_hours' => ['nullable', 'integer', 'between:1,2500'],
            'modality_code' => ['nullable', 'string', 'max:60'],
            'formation_type_code' => ['nullable', 'string', 'max:60'],
            'curriculum_track' => ['nullable', 'string', 'max:30'],
            'publish_now' => ['sometimes', 'boolean'],
            'source_file' => ['nullable', File::types(['pdf'])->max($maxPdfKb), 'required_if:publish_now,1'],
            'source_page_count' => ['nullable', 'integer', 'between:1,5000'],
            'objective_ids' => ['required', 'array', 'min:1', 'max:500'],
            'objective_ids.*' => ['required', 'integer', 'distinct', 'exists:lcd_learning_objectives,id'],
            'axes' => ['nullable', 'array', 'max:30'],
            'axes.*.name' => ['required', 'string', 'max:190', 'distinct:strict'],
            'axes.*.description' => ['nullable', 'string', 'max:3000'],
            'axes.*.objective_ids' => ['nullable', 'array', 'max:500'],
            'axes.*.objective_ids.*' => ['integer', 'distinct', 'exists:lcd_learning_objectives,id'],
            'units' => ['required', 'array', 'min:1', 'max:30'],
            'units.*.unit_code' => ['required', 'string', 'max:80', 'distinct'],
            'units.*.official_title' => ['required', 'string', 'max:190'],
            'units.*.purpose' => ['nullable', 'string', 'max:10000'],
            'units.*.semester' => ['nullable', 'integer', 'between:1,3'],
            'units.*.estimated_pedagogical_hours' => ['nullable', 'integer', 'between:1,1000'],
            'units.*.page_start' => ['nullable', 'integer', 'between:1,5000'],
            'units.*.page_end' => ['nullable', 'integer', 'gte:units.*.page_start', 'between:1,5000'],
            'units.*.objective_ids' => ['required', 'array', 'min:1', 'max:500'],
            'units.*.objective_ids.*' => ['required', 'integer', 'distinct', 'exists:lcd_learning_objectives,id'],
            'units.*.skills' => ['nullable', 'array', 'max:100'],
            'units.*.skills.*' => ['string', 'max:2000'],
            'units.*.attitudes' => ['nullable', 'array', 'max:100'],
            'units.*.attitudes.*' => ['string', 'max:2000'],
            'units.*.knowledge' => ['nullable', 'array', 'max:100'],
            'units.*.knowledge.*' => ['string', 'max:4000'],
            'units.*.keywords' => ['nullable', 'array', 'max:100'],
            'units.*.keywords.*' => ['string', 'max:190'],
        ];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return [
            'source_file.required_if' => 'Adjunta el PDF de respaldo antes de publicar el programa.',
            'objective_ids.required' => 'Selecciona los objetivos de aprendizaje del programa.',
            'units.required' => 'Agrega al menos una unidad curricular.',
            'units.*.objective_ids.required' => 'Cada unidad debe incluir al menos un objetivo.',
        ];
    }
}
