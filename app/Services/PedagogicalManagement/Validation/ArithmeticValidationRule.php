<?php

namespace App\Services\PedagogicalManagement\Validation;

use App\Contracts\PedagogicalManagement\InstrumentValidationRule;
use App\DTO\PedagogicalManagement\InstrumentAnalysisContext;

class ArithmeticValidationRule implements InstrumentValidationRule
{
    use ValidationRuleSupport;

    public function validate(InstrumentAnalysisContext $context): array
    {
        $points = (array) data_get($context->extractedData, 'points', []);
        $declared = collect((array) ($points['declared_totals'] ?? []));
        $distinctDeclared = $declared->pluck('value')->map(fn ($v): float => (float) $v)->unique()->values();
        $computed = $points['computed_total_points'] ?? null;
        $reliability = (string) ($points['computation_reliability'] ?? 'not_evaluable');
        $sheetDeclared = $context->instrument->declared_total_points !== null ? (float) $context->instrument->declared_total_points : null;
        $pdfDeclared = $distinctDeclared->count() === 1 ? (float) $distinctDeclared->first() : null;
        $percentages = collect((array) data_get($context->extractedData, 'percentages', []));
        $duplicateComponents = (array) ($points['duplicate_components'] ?? []);
        $findings = [];

        $findings[] = $distinctDeclared->count() > 1
            ? $this->finding('MULTIPLE_DECLARED_TOTALS', 'arithmetic', 'error', 'fail', 'exact', 'Múltiples puntajes totales declarados', 'El PDF contiene valores totales distintos.', ['values' => $distinctDeclared->all()], blocking: true)
            : $this->pass('MULTIPLE_DECLARED_TOTALS', 'arithmetic', 'Puntaje total no ambiguo', 'Se detectó como máximo un valor total distinto.');
        $findings[] = $sheetDeclared !== null && $pdfDeclared !== null && abs($sheetDeclared - $pdfDeclared) > 0.001
            ? $this->finding('DECLARED_TOTAL_CONFLICT', 'arithmetic', 'error', 'fail', 'exact', 'Conflicto entre ficha y PDF', 'El puntaje total registrado en la ficha difiere del declarado en el documento.', ['pdf' => $pdfDeclared], ['sheet' => $sheetDeclared], blocking: true)
            : $this->pass('DECLARED_TOTAL_CONFLICT', 'arithmetic', 'Ficha y PDF sin conflicto', 'No se detectó una diferencia exacta entre ambos valores.');
        if ($computed !== null && $reliability === 'exact' && ($sheetDeclared !== null || $pdfDeclared !== null)) {
            $expected = $sheetDeclared ?? $pdfDeclared;
            $difference = round((float) $expected - (float) $computed, 2);
            $findings[] = abs($difference) > 0.001
                ? $this->finding('DECLARED_COMPUTED_TOTAL_MISMATCH', 'arithmetic', 'critical', 'fail', 'exact', 'El puntaje total no coincide con el desglose', 'La suma reproducible de componentes difiere del total declarado.', ['declared' => $expected, 'computed' => (float) $computed, 'difference' => abs($difference)], ['difference' => 0], blocking: true)
                : $this->pass('DECLARED_COMPUTED_TOTAL_MISMATCH', 'arithmetic', 'Total aritmético consistente', 'La suma exacta de componentes coincide con el total declarado.');
        } else {
            $findings[] = $this->notEvaluable('DECLARED_COMPUTED_TOTAL_MISMATCH', 'arithmetic', 'Total no evaluable automáticamente', 'No existe un desglose completo, no superpuesto y de fiabilidad exacta.');
        }

        $findings[] = $this->notEvaluable('SECTION_TOTAL_MISMATCH', 'arithmetic', 'Totales internos de sección no evaluables', 'No se identificó una jerarquía completa de ítems y subtotales sin superposición.');
        $findings[] = $this->notEvaluable('RUBRIC_MAX_SCORE_MISMATCH', 'arithmetic', 'Máximo de rúbrica no evaluable', 'Los niveles y criterios no forman una matriz completa inequívoca para calcular un máximo.');
        $findings[] = $this->notEvaluable('PERFORMANCE_TABLE_TOTAL_MISMATCH', 'arithmetic', 'Tabla de desempeño no evaluable', 'La tabla no ofrece un desglose completo inequívoco en texto lineal.');
        $invalidPercentages = $percentages->filter(fn (array $value): bool => (float) $value['value'] < 0 || (float) $value['value'] > 100);
        $findings[] = $invalidPercentages->isNotEmpty()
            ? $this->finding('PERCENTAGE_OUT_OF_RANGE', 'arithmetic', 'error', 'fail', 'exact', 'Porcentaje fuera de rango', 'Se detectó un porcentaje menor que 0 o mayor que 100.', ['values' => $invalidPercentages->pluck('value')->all()], blocking: true)
            : $this->pass('PERCENTAGE_OUT_OF_RANGE', 'arithmetic', 'Porcentajes dentro de rango', 'Los porcentajes detectados están entre 0 y 100.');
        $weighting = $percentages->filter(fn (array $value): bool => preg_match('/ponder|promedio|evaluaci[oó]n|actividad/iu', (string) ($value['context'] ?? '')) === 1)->pluck('value')->unique();
        $findings[] = $weighting->count() > 1
            ? $this->finding('MULTIPLE_WEIGHTING_PERCENTAGES', 'arithmetic', 'warning', 'warning', 'heuristic', 'Múltiples ponderaciones detectadas', 'El PDF contiene más de un porcentaje con contexto de ponderación.', ['values' => $weighting->all()])
            : $this->pass('MULTIPLE_WEIGHTING_PERCENTAGES', 'arithmetic', 'Ponderación no ambigua', 'Se detectó como máximo una ponderación contextual.');
        $findings[] = ($context->instrument->minimum_grade !== null && $context->instrument->maximum_grade !== null && (float) $context->instrument->minimum_grade >= (float) $context->instrument->maximum_grade)
            ? $this->finding('GRADE_SCALE_INVALID', 'arithmetic', 'error', 'fail', 'exact', 'Escala de notas inválida', 'La nota mínima no es menor que la máxima.', blocking: true)
            : $this->pass('GRADE_SCALE_INVALID', 'arithmetic', 'Escala sin conflicto', 'La escala está incompleta o conserva un orden válido.');
        $itemsWithoutPoints = (array) data_get($context->extractedData, 'items_without_points', []);
        $findings[] = $itemsWithoutPoints !== []
            ? $this->finding('ITEM_WITHOUT_POINTS', 'arithmetic', 'warning', 'warning', 'heuristic', 'Ítem sin puntaje detectable', 'Uno o más encabezados de ítem no incluyen puntaje en la misma línea.', ['count' => count($itemsWithoutPoints)])
            : $this->pass('ITEM_WITHOUT_POINTS', 'arithmetic', 'Ítems sin omisión mecánica', 'No se detectaron encabezados de ítem sin puntaje en su misma línea.');
        $findings[] = $declared->isNotEmpty() && $computed === null
            ? $this->finding('POINTS_WITHOUT_BREAKDOWN', 'arithmetic', 'warning', 'not_evaluable', 'not_evaluable', 'Puntaje sin desglose calculable', 'Existe un total, pero los componentes no permiten una suma segura.')
            : $this->pass('POINTS_WITHOUT_BREAKDOWN', 'arithmetic', 'Desglose disponible o total ausente', 'No se detectó el caso total-sin-desglose.');
        $findings[] = $duplicateComponents !== []
            ? $this->finding('DUPLICATED_POINT_COMPONENT', 'arithmetic', 'warning', 'warning', 'exact', 'Componentes de puntaje duplicados', 'El mismo componente apareció más de una vez; no se sumó para evitar superposición.', ['labels' => $duplicateComponents])
            : $this->pass('DUPLICATED_POINT_COMPONENT', 'arithmetic', 'Componentes sin duplicados', 'No se detectaron etiquetas de componente repetidas.');

        return $findings;
    }
}
