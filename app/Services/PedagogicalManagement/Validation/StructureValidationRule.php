<?php

namespace App\Services\PedagogicalManagement\Validation;

use App\Contracts\PedagogicalManagement\InstrumentValidationRule;
use App\DTO\PedagogicalManagement\InstrumentAnalysisContext;

class StructureValidationRule implements InstrumentValidationRule
{
    use ValidationRuleSupport;

    public function validate(InstrumentAnalysisContext $context): array
    {
        $sections = (array) data_get($context->extractedData, 'sections', []);
        $sectionFinding = fn (string $code, string $key, string $title): array => isset($sections[$key])
            ? $this->pass($code, 'structure', $title, 'Se detectó un encabezado o marcador reproducible en el PDF.')
            : $this->finding($code, 'structure', 'warning', 'warning', 'heuristic', $title, 'No se detectó un encabezado reconocible. La ausencia mecánica no equivale a una conclusión pedagógica.');
        $points = (array) data_get($context->extractedData, 'points', []);
        $rubric = (array) data_get($context->extractedData, 'rubric', []);
        $performance = (array) data_get($context->extractedData, 'performance_record', []);
        $accessibility = data_get($context->extractedData, 'generic_accessibility_declaration');

        return [
            $sectionFinding('LEARNING_OBJECTIVE_SECTION_MISSING', 'learning_objectives', 'Sección de objetivos de aprendizaje'),
            $sectionFinding('EVALUATION_OBJECTIVE_MISSING', 'evaluation_objective', 'Objetivo de evaluación'),
            $sectionFinding('INDICATORS_SECTION_MISSING', 'indicators', 'Sección de indicadores'),
            $sectionFinding('CONTENT_SECTION_MISSING', 'content', 'Sección de contenido'),
            $sectionFinding('INSTRUCTIONS_SECTION_MISSING', 'instructions', 'Instrucciones generales'),
            data_get($context->extractedData, 'duration')
                ? $this->pass('DURATION_NOT_FOUND_IN_DOCUMENT', 'structure', 'Duración detectada', 'Se detectó una duración expresada en minutos.')
                : $this->finding('DURATION_NOT_FOUND_IN_DOCUMENT', 'structure', 'warning', 'warning', 'heuristic', 'Duración no encontrada en el documento', 'La ficha puede registrar duración, pero no se encontró una expresión inequívoca en el PDF.'),
            count((array) ($points['declared_totals'] ?? [])) > 0
                ? $this->pass('TOTAL_POINTS_NOT_FOUND_IN_DOCUMENT', 'structure', 'Puntaje total detectado', 'Se detectó al menos una declaración inequívoca de puntaje total.')
                : $this->finding('TOTAL_POINTS_NOT_FOUND_IN_DOCUMENT', 'structure', 'warning', 'warning', 'heuristic', 'Puntaje total no encontrado', 'No se detectó una declaración inequívoca de puntaje total.'),
            isset($sections['metacognition']) && collect((array) ($points['components'] ?? []))->where('label', 'metacognicion')->isEmpty()
                ? $this->finding('METACOGNITION_DECLARED_WITHOUT_POINTS', 'structure', 'warning', 'warning', 'heuristic', 'Metacognición sin puntaje identificable', 'Se detectó metacognición, pero no un componente de puntaje inequívoco.')
                : $this->pass('METACOGNITION_DECLARED_WITHOUT_POINTS', 'structure', 'Metacognición sin conflicto mecánico', 'No se detectó la combinación sección-sin-puntaje.'),
            ($rubric['detected'] ?? false) && (int) ($rubric['levels_detected'] ?? 0) < 2
                ? $this->finding('RUBRIC_DETECTED_WITHOUT_LEVELS', 'structure', 'warning', 'warning', 'heuristic', 'Rúbrica sin niveles identificables', 'Se detectó una rúbrica, pero no al menos dos niveles de puntaje reconocibles.')
                : $this->pass('RUBRIC_DETECTED_WITHOUT_LEVELS', 'structure', 'Niveles de rúbrica sin conflicto', 'No se detectó una rúbrica carente de niveles mecánicamente reconocibles.'),
            ($rubric['detected'] ?? false) && ! ($rubric['criteria_identifiable'] ?? false)
                ? $this->finding('RUBRIC_CRITERIA_NOT_IDENTIFIABLE', 'structure', 'warning', 'warning', 'heuristic', 'Criterios de rúbrica no identificables', 'La palabra rúbrica aparece, pero no se reconoce un marcador de criterios.')
                : $this->pass('RUBRIC_CRITERIA_NOT_IDENTIFIABLE', 'structure', 'Criterios sin conflicto mecánico', 'No se detectó una rúbrica sin marcadores de criterio.'),
            ($performance['detected'] ?? false) && ! ($performance['has_total'] ?? false)
                ? $this->finding('PERFORMANCE_RECORD_WITHOUT_TOTAL', 'structure', 'warning', 'warning', 'heuristic', 'Registro de desempeño sin total', 'Se detectó la sección, pero no un total cercano e inequívoco.')
                : $this->pass('PERFORMANCE_RECORD_WITHOUT_TOTAL', 'structure', 'Registro de desempeño sin conflicto', 'No se detectó la combinación registro-sin-total.'),
            $accessibility && blank($context->instrument->accessibility_measures)
                ? $this->finding('GENERIC_ACCESSIBILITY_DECLARATION_ONLY', 'structure', 'warning', 'warning', 'heuristic', 'Declaración genérica de accesibilidad', 'El PDF contiene una frase general de acceso o adecuación, pero la ficha no registra medidas concretas. Esto no constituye una conclusión legal.', excerpt: $accessibility['source_excerpt'] ?? null, page: $accessibility['page'] ?? null)
                : $this->pass('GENERIC_ACCESSIBILITY_DECLARATION_ONLY', 'structure', 'Accesibilidad sin advertencia genérica', 'No se detectó una declaración genérica aislada o la ficha sí contiene medidas concretas.'),
        ];
    }
}
