<?php

namespace App\Services\PedagogicalManagement\Validation;

use App\Contracts\PedagogicalManagement\InstrumentValidationRule;
use App\DTO\PedagogicalManagement\InstrumentAnalysisContext;
use App\Models\LibroDigital\LearningObjective;
use App\Services\PedagogicalManagement\InstrumentTextNormalizer;

class CurriculumValidationRule implements InstrumentValidationRule
{
    use ValidationRuleSupport;

    public function __construct(private readonly InstrumentTextNormalizer $normalizer) {}

    public function validate(InstrumentAnalysisContext $context): array
    {
        $instrument = $context->instrument;
        $selectedRelations = $instrument->objectives->where('origin', 'manual');
        $selected = $selectedRelations->map(fn ($relation): ?array => $relation->learningObjective ? [
            'id' => $relation->learningObjective->id,
            'code' => $relation->learningObjective->code,
            'number' => $this->objectiveNumber($relation->learningObjective->code),
            'subject_id' => $relation->learningObjective->schedule_subject_id,
            'grade_code' => $relation->learningObjective->grade_code,
            'catalog_id' => $relation->learningObjective->curriculum_catalog_id,
        ] : null)->filter()->values();
        $detected = collect((array) data_get($context->extractedData, 'learning_objectives', []));
        $selectedNumbers = $selected->pluck('number')->filter()->unique()->values();
        $detectedNumbers = $detected->pluck('number')->map(fn ($value): int => (int) $value)->unique()->values();
        $findings = [];

        $findings[] = $selected->isEmpty()
            ? $this->finding('OA_NOT_SELECTED', 'curriculum', 'warning', 'warning', 'exact', 'No hay OA seleccionados', 'Selecciona uno o más OA del catálogo curricular para efectuar comparaciones mecánicas.', fieldPath: 'learning_objective_ids')
            : $this->pass('OA_NOT_SELECTED', 'curriculum', 'OA seleccionados', 'La ficha contiene asociaciones curriculares manuales confirmadas.');
        $findings[] = $detected->isEmpty()
            ? $this->finding('OA_CODE_NOT_DETECTED_IN_PDF', 'curriculum', 'warning', 'not_evaluable', 'heuristic', 'No se detectaron códigos OA', 'El PDF no contiene códigos OA reconocibles; esto no demuestra ausencia de objetivos pedagógicos.')
            : $this->pass('OA_CODE_NOT_DETECTED_IN_PDF', 'curriculum', 'Códigos OA detectados', 'Se encontraron referencias con marcador OA y número.');

        foreach ($detected->whereNotIn('number', $selectedNumbers->all()) as $candidate) {
            $findings[] = $this->finding(
                'OA_DETECTED_NOT_SELECTED', 'curriculum', 'error', 'fail', (string) ($candidate['reliability'] ?? 'exact'),
                'OA detectado no seleccionado', 'El PDF menciona un OA que no coincide con la selección manual. Revisa cuál referencia corresponde; el sistema no decide por ti.',
                ['code' => $candidate['normalized_code']], ['selected_codes' => $selected->pluck('code')->all()],
                excerpt: $candidate['source_excerpt'] ?? null, page: $candidate['page'] ?? null, blocking: true,
            );
        }
        if ($detected->whereNotIn('number', $selectedNumbers->all())->isEmpty()) {
            $findings[] = $this->pass('OA_DETECTED_NOT_SELECTED', 'curriculum', 'OA detectados compatibles', 'No se encontraron códigos detectados fuera de la selección manual.');
        }
        foreach ($selected->whereNotIn('number', $detectedNumbers->all()) as $objective) {
            $findings[] = $this->finding(
                'OA_SELECTED_NOT_DETECTED', 'curriculum', 'warning', 'warning', 'exact',
                'OA seleccionado no detectado', 'El OA seleccionado en la ficha no aparece como código reconocible en el PDF. Requiere revisión humana.',
                ['detected_codes' => $detected->pluck('normalized_code')->all()], ['selected_code' => $objective['code']],
            );
        }
        if ($selected->whereNotIn('number', $detectedNumbers->all())->isEmpty()) {
            $findings[] = $this->pass('OA_SELECTED_NOT_DETECTED', 'curriculum', 'OA seleccionados referenciados', 'Los números de OA seleccionados aparecen en el documento.');
        }

        $catalogObjectives = $detectedNumbers->isEmpty()
            ? collect()
            : LearningObjective::query()
                ->where('active', true)
                ->where(function ($query) use ($detectedNumbers): void {
                    foreach ($detectedNumbers as $number) {
                        $query->orWhere('code', 'like', '%'.$number.'%');
                    }
                })
                ->get(['id', 'code', 'schedule_subject_id', 'grade_code', 'curriculum_catalog_id'])
                ->filter(fn (LearningObjective $objective): bool => $detectedNumbers->contains($this->objectiveNumber($objective->code)));
        foreach ($detected as $candidate) {
            $matches = $catalogObjectives->filter(fn (LearningObjective $objective): bool => $this->objectiveNumber($objective->code) === (int) $candidate['number']);
            if ($matches->isEmpty()) {
                $findings[] = $this->finding('OA_NOT_FOUND_IN_CATALOG', 'curriculum', 'warning', 'warning', 'exact', 'OA no encontrado en catálogo', 'El código detectado no tiene coincidencia numérica en el catálogo activo.', ['code' => $candidate['normalized_code']], excerpt: $candidate['source_excerpt'] ?? null, page: $candidate['page'] ?? null);
            }
        }
        if (! collect($findings)->contains('code', 'OA_NOT_FOUND_IN_CATALOG')) {
            $findings[] = $this->pass('OA_NOT_FOUND_IN_CATALOG', 'curriculum', 'OA localizables en catálogo', 'Los códigos detectados tienen al menos una coincidencia numérica activa.');
        }

        $subjectMismatch = $selected->filter(fn (array $objective): bool => $objective['subject_id'] !== null && (int) $objective['subject_id'] !== (int) $instrument->subject_id);
        $findings[] = $subjectMismatch->isNotEmpty()
            ? $this->finding('OA_SUBJECT_MISMATCH', 'curriculum', 'error', 'fail', 'exact', 'OA de otra asignatura', 'Uno o más OA seleccionados pertenecen a una asignatura distinta.', ['codes' => $subjectMismatch->pluck('code')->all()], ['subject_id' => $instrument->subject_id], blocking: true)
            : $this->pass('OA_SUBJECT_MISMATCH', 'curriculum', 'Asignatura compatible', 'No se detectaron asociaciones explícitas a otra asignatura.');

        $courseKeys = $instrument->courses->map(fn ($course): string => $this->normalizer->searchKey(trim(($course->educationLevel?->name ?? '').' '.$course->display_name)))->filter();
        $gradeMismatch = $selected->filter(function (array $objective) use ($courseKeys): bool {
            $grade = $this->normalizer->searchKey((string) $objective['grade_code']);
            return $grade !== '' && $courseKeys->isNotEmpty() && ! $courseKeys->contains(fn (string $course): bool => str_contains($course, $grade) || str_contains($grade, $course));
        });
        $findings[] = $gradeMismatch->isNotEmpty()
            ? $this->finding('OA_GRADE_MISMATCH', 'curriculum', 'warning', 'warning', 'heuristic', 'Nivel del OA requiere revisión', 'La comparación léxica entre grade_code y los cursos no encontró coincidencia.', ['codes' => $gradeMismatch->pluck('code')->all()], ['courses' => $instrument->courses->pluck('display_name')->all()])
            : $this->pass('OA_GRADE_MISMATCH', 'curriculum', 'Nivel sin conflicto mecánico', 'No se detectó un conflicto explícito de nivel.');

        $duplicates = $selected->groupBy('id')->filter(fn ($items): bool => $items->count() > 1);
        $findings[] = $duplicates->isNotEmpty()
            ? $this->finding('OA_DUPLICATED', 'curriculum', 'error', 'fail', 'exact', 'OA duplicado', 'La ficha contiene asociaciones repetidas.', ['objective_ids' => $duplicates->keys()->all()], blocking: true)
            : $this->pass('OA_DUPLICATED', 'curriculum', 'OA sin duplicados', 'La selección manual no contiene OA repetidos.');
        $findings[] = $this->notEvaluable('OA_CODE_TEXT_REFERENCE_CONFLICT', 'curriculum', 'Texto del OA no evaluable automáticamente', 'Esta fase no realiza análisis semántico del texto oficial ni del instrumento.');
        $findings[] = $selected->pluck('catalog_id')->filter()->unique()->count() > 1
            ? $this->finding('MULTIPLE_CURRICULUM_VERSIONS', 'curriculum', 'warning', 'warning', 'exact', 'Múltiples versiones curriculares', 'Los OA seleccionados provienen de más de un catálogo curricular.', ['catalog_ids' => $selected->pluck('catalog_id')->unique()->values()->all()])
            : $this->pass('MULTIPLE_CURRICULUM_VERSIONS', 'curriculum', 'Versión curricular consistente', 'La selección utiliza como máximo un catálogo curricular.');

        return $findings;
    }

    private function objectiveNumber(string $code): ?int
    {
        preg_match_all('/\d{1,3}/', $code, $matches);
        $numbers = $matches[0] ?? [];

        return $numbers === [] ? null : (int) end($numbers);
    }
}
