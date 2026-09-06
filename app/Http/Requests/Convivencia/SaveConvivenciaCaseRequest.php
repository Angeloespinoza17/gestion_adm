<?php

namespace App\Http\Requests\Convivencia;

use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaCatalogItem;
use App\Models\CourseSection;
use App\Services\Convivencia\ConvivenciaSupportProfessionalService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

class SaveConvivenciaCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'case_type_item_id' => ['nullable', 'integer', $this->catalogItemRule('case_type', 'case_type_item_id')],
            'classification_item_id' => ['required', 'integer', $this->catalogItemRule('classification', 'classification_item_id')],
            'subclassification_item_id' => ['nullable', 'integer', $this->catalogItemRule('subclassification', 'subclassification_item_id')],
            'criticality_item_id' => ['required', 'integer', $this->catalogItemRule('criticality', 'criticality_item_id')],
            'responsible_user_id' => ['required', 'integer', 'exists:users,id'],
            'responsible_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'opened_at' => ['required', 'date'],
            'happened_at' => ['nullable', 'date', 'before_or_equal:opened_at'],
            'origin' => ['required', Rule::in(array_column(ConvivenciaCase::ORIGIN_OPTIONS, 'value'))],
            'status' => ['nullable', Rule::in(array_column(ConvivenciaCase::STATUS_OPTIONS, 'value'))],
            'case_type_label' => ['nullable', 'string', 'max:160'],
            'classification_label' => ['nullable', 'string', 'max:160'],
            'subclassification_label' => ['nullable', 'string', 'max:160'],
            'criticality_label' => ['nullable', 'string', 'max:100'],
            'place' => ['nullable', 'string', 'max:160'],
            'initial_report' => ['required', 'string', 'min:10'],
            'background' => ['nullable', 'string'],
            'immediate_measures' => ['nullable', 'string'],
            'safeguarding_measures' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'resolution' => ['nullable', 'string'],
            'conclusion' => ['nullable', 'string'],
            'follow_up_due_at' => ['nullable', 'date', 'after_or_equal:opened_at'],
            'is_sensitive' => ['sometimes', 'boolean'],
            'people' => ['nullable', 'array'],
            'people.*.student_profile_id' => ['nullable', 'integer', 'exists:student_profiles,id'],
            'people.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'people.*.staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'people.*.course_section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'people.*.person_type' => ['required_with:people', Rule::in(array_column(ConvivenciaCase::PERSON_TYPE_OPTIONS, 'value'))],
            'people.*.role_type' => ['required_with:people', Rule::in(array_column(ConvivenciaCase::PERSON_ROLE_OPTIONS, 'value'))],
            'people.*.full_name' => ['required_with:people', 'string', 'max:191'],
            'people.*.identifier' => ['nullable', 'string', 'max:80'],
            'people.*.relationship_label' => ['nullable', 'string', 'max:120'],
            'people.*.contact_reference' => ['nullable', 'string', 'max:191'],
            'people.*.notes' => ['nullable', 'string'],
            'people.*.is_sensitive' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $validator->errors()->hasAny(['classification_item_id', 'subclassification_item_id'])) {
                $classificationId = (int) $this->input('classification_item_id');
                $subclassificationId = (int) $this->input('subclassification_item_id');

                if ($classificationId && $subclassificationId) {
                    $subclassification = ConvivenciaCatalogItem::query()
                        ->whereKey($subclassificationId)
                        ->where('group', 'subclassification')
                        ->first(['id', 'parent_id']);

                    if ($subclassification?->parent_id && (int) $subclassification->parent_id !== $classificationId) {
                        $validator->errors()->add(
                            'subclassification_item_id',
                            'La subclasificación no pertenece a la clasificación seleccionada.',
                        );
                    }
                }
            }

            $academicYearId = (int) $this->input('academic_year_id');
            $courseSectionId = (int) $this->input('course_section_id');
            if ($academicYearId && $courseSectionId && ! CourseSection::query()
                ->whereKey($courseSectionId)
                ->where('academic_year_id', $academicYearId)
                ->exists()) {
                $validator->errors()->add(
                    'course_section_id',
                    'El curso no pertenece al año académico seleccionado.',
                );
            }

            $people = collect($this->input('people', []))->filter(fn ($person) => is_array($person));
            $seenStudents = [];
            $seenProfessionals = [];
            $hasSupportProfessionals = $people->contains(fn (array $person) => ($person['role_type'] ?? null) === 'profesional_apoyo');
            $eligibleProfessionals = $hasSupportProfessionals
                ? app(ConvivenciaSupportProfessionalService::class)->options()
                : collect();

            foreach ($people as $index => $person) {
                $studentId = (int) ($person['student_profile_id'] ?? 0);
                if ($studentId) {
                    if (isset($seenStudents[$studentId])) {
                        $validator->errors()->add("people.{$index}.student_profile_id", 'Esta alumna ya está vinculada al caso.');
                    }
                    $seenStudents[$studentId] = true;
                }

                if (($person['role_type'] ?? null) !== 'profesional_apoyo') {
                    continue;
                }

                if (($person['person_type'] ?? null) !== 'funcionario') {
                    $validator->errors()->add("people.{$index}.person_type", 'Un profesional de apoyo debe estar registrado como funcionario.');

                    continue;
                }

                $userId = (int) ($person['user_id'] ?? 0);
                $staffId = (int) ($person['staff_id'] ?? 0);
                $eligible = $eligibleProfessionals->first(function (array $professional) use ($userId, $staffId): bool {
                    if (! $userId && ! $staffId) {
                        return false;
                    }

                    return (! $userId || (int) ($professional['user_id'] ?? 0) === $userId)
                        && (! $staffId || (int) ($professional['staff_id'] ?? 0) === $staffId);
                });

                if (! $eligible) {
                    $validator->errors()->add("people.{$index}.staff_id", 'Selecciona un profesional desde el equipo de apoyo institucional.');

                    continue;
                }

                $professionalKey = ($staffId ? 'staff:'.$staffId : 'user:'.$userId);
                if (isset($seenProfessionals[$professionalKey])) {
                    $validator->errors()->add("people.{$index}.staff_id", 'Este profesional ya forma parte del equipo de apoyo del caso.');
                }
                $seenProfessionals[$professionalKey] = true;
            }
        });
    }

    private function catalogItemRule(string $group, string $field): Exists
    {
        $case = $this->route('case');
        $currentId = $case instanceof ConvivenciaCase ? (int) ($case->{$field} ?? 0) : 0;

        return Rule::exists('convivencia_catalog_items', 'id')->where(function ($query) use ($group, $currentId): void {
            $query->where('group', $group)
                ->where(function ($activeQuery) use ($currentId): void {
                    $activeQuery->where('active', true);
                    if ($currentId) {
                        $activeQuery->orWhere('id', $currentId);
                    }
                });
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'classification_item_id.required' => 'Selecciona una clasificación para el caso.',
            'criticality_item_id.required' => 'Selecciona el nivel de criticidad.',
            'responsible_user_id.required' => 'Selecciona a la persona responsable del caso.',
            'opened_at.required' => 'Indica la fecha y hora de apertura.',
            'initial_report.required' => 'Registra el relato inicial del caso.',
            'initial_report.min' => 'El relato inicial debe contener al menos 10 caracteres.',
            'happened_at.before_or_equal' => 'La fecha del hecho no puede ser posterior a la apertura del caso.',
            'follow_up_due_at.after_or_equal' => 'El próximo seguimiento no puede ser anterior a la apertura del caso.',
        ];
    }
}
