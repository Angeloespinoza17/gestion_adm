<?php

namespace App\Services\PedagogicalManagement\Validation;

use App\Contracts\PedagogicalManagement\InstrumentValidationRule;
use App\DTO\PedagogicalManagement\InstrumentAnalysisContext;

class TechnicalSheetValidationRule implements InstrumentValidationRule
{
    use ValidationRuleSupport;

    public function validate(InstrumentAnalysisContext $context): array
    {
        $instrument = $context->instrument;
        $requiresPoints = in_array($instrument->instrument_type->value, [
            'written_test', 'quiz', 'rubric', 'checklist', 'appreciation_scale', 'assessed_guide', 'practical_assessment',
        ], true);
        $year = $instrument->academicYear;
        $dateOutside = $instrument->application_date && $year
            && ($instrument->application_date->lt($year->starts_at) || $instrument->application_date->gt($year->ends_at));
        $missing = fn (string $code, string $title, string $field, mixed $value, bool $blocking = true): array => filled($value)
            ? $this->pass($code, 'technical_sheet', $title, 'El campo fue registrado en la ficha técnica.')
            : $this->finding($code, 'technical_sheet', $blocking ? 'error' : 'warning', $blocking ? 'fail' : 'warning', 'exact', $title, 'Completa este dato en la ficha técnica.', fieldPath: $field, blocking: $blocking);

        return [
            $missing('TITLE_MISSING', 'Título requerido', 'title', $instrument->title),
            $missing('ACADEMIC_YEAR_MISSING', 'Año académico requerido', 'academic_year_id', $instrument->academic_year_id),
            $missing('SUBJECT_MISSING', 'Asignatura requerida', 'subject_id', $instrument->subject_id),
            $missing('COURSE_MISSING', 'Curso requerido', 'course_ids', $instrument->courses->isNotEmpty()),
            $missing('TEACHER_MISSING', 'Docente responsable requerido', 'owner_user_id', $instrument->owner_user_id),
            $missing('INSTRUMENT_TYPE_MISSING', 'Tipo de instrumento requerido', 'instrument_type', $instrument->instrument_type?->value),
            $missing('EVALUATION_PURPOSE_MISSING', 'Propósito evaluativo requerido', 'evaluation_purpose', $instrument->evaluation_purpose?->value),
            $missing('DURATION_MISSING', 'Duración no registrada', 'duration_minutes', $instrument->duration_minutes, false),
            $instrument->declared_total_points !== null || ! $requiresPoints
                ? $this->pass('DECLARED_TOTAL_POINTS_MISSING', 'technical_sheet', 'Puntaje declarado registrado', $requiresPoints ? 'La ficha incluye puntaje total.' : 'El tipo de instrumento no obliga a declarar puntaje en esta fase.')
                : $this->finding('DECLARED_TOTAL_POINTS_MISSING', 'technical_sheet', 'warning', 'warning', 'exact', 'Puntaje total no registrado', 'Este tipo de instrumento requiere puntaje total para validar su aritmética.', fieldPath: 'declared_total_points'),
            $instrument->duration_minutes !== null && ((int) $instrument->duration_minutes < 1 || (int) $instrument->duration_minutes > 600)
                ? $this->finding('INVALID_DURATION', 'technical_sheet', 'error', 'fail', 'exact', 'Duración inválida', 'La duración debe estar entre 1 y 600 minutos.', ['minutes' => $instrument->duration_minutes], ['range' => [1, 600]], fieldPath: 'duration_minutes', blocking: true)
                : $this->pass('INVALID_DURATION', 'technical_sheet', 'Duración válida', 'La duración está vacía o dentro del rango permitido.'),
            $instrument->declared_total_points !== null && (float) $instrument->declared_total_points <= 0
                ? $this->finding('INVALID_TOTAL_POINTS', 'technical_sheet', 'error', 'fail', 'exact', 'Puntaje total inválido', 'El puntaje total debe ser mayor que cero.', fieldPath: 'declared_total_points', blocking: true)
                : $this->pass('INVALID_TOTAL_POINTS', 'technical_sheet', 'Puntaje total válido', 'El puntaje está vacío o es mayor que cero.'),
            $this->percentageFinding('INVALID_PASSING_PERCENTAGE', 'Porcentaje de exigencia inválido', 'passing_percentage', $instrument->passing_percentage),
            $this->percentageFinding('INVALID_WEIGHTING_PERCENTAGE', 'Ponderación inválida', 'weighting_percentage', $instrument->weighting_percentage),
            ($instrument->minimum_grade !== null && $instrument->maximum_grade !== null && (float) $instrument->minimum_grade >= (float) $instrument->maximum_grade)
                ? $this->finding('INVALID_GRADE_RANGE', 'technical_sheet', 'error', 'fail', 'exact', 'Rango de calificación inválido', 'La nota mínima debe ser menor que la máxima.', ['minimum' => $instrument->minimum_grade, 'maximum' => $instrument->maximum_grade], fieldPath: 'minimum_grade', blocking: true)
                : $this->pass('INVALID_GRADE_RANGE', 'technical_sheet', 'Rango de calificación válido', 'La escala está incompleta o conserva un orden válido.'),
            $dateOutside
                ? $this->finding('APPLICATION_DATE_OUTSIDE_ACADEMIC_YEAR', 'technical_sheet', 'error', 'fail', 'exact', 'Fecha fuera del año académico', 'La fecha estimada no pertenece al intervalo del año académico seleccionado.', ['date' => $instrument->application_date?->toDateString()], ['starts_at' => $year?->starts_at?->toDateString(), 'ends_at' => $year?->ends_at?->toDateString()], fieldPath: 'application_date', blocking: true)
                : $this->pass('APPLICATION_DATE_OUTSIDE_ACADEMIC_YEAR', 'technical_sheet', 'Fecha compatible', 'La fecha está vacía o pertenece al año académico.'),
        ];
    }

    private function percentageFinding(string $code, string $title, string $field, mixed $value): array
    {
        if ($value !== null && ((float) $value < 0 || (float) $value > 100)) {
            return $this->finding($code, 'technical_sheet', 'error', 'fail', 'exact', $title, 'El porcentaje debe estar entre 0 y 100.', ['value' => $value], ['range' => [0, 100]], fieldPath: $field, blocking: true);
        }

        return $this->pass($code, 'technical_sheet', str_replace(['inválido', 'inválida'], ['válido', 'válida'], $title), 'El porcentaje está vacío o dentro del rango permitido.');
    }
}
