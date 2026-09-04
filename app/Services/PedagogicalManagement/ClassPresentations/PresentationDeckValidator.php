<?php

namespace App\Services\PedagogicalManagement\ClassPresentations;

use App\Exceptions\PedagogicalManagement\ClassPresentationGenerationException;
use App\Models\PedagogicalManagement\ClassPresentation;

class PresentationDeckValidator
{
    public function __construct(private readonly PresentationStyleContract $styleContract) {}

    /** @param array<string,mixed> $deck */
    public function validate(ClassPresentation $presentation, array $deck): void
    {
        $expectedSlides = (int) data_get($presentation->configuration, 'slide_count');
        $slides = $deck['slides'] ?? null;
        if (! is_array($slides) || count($slides) !== $expectedSlides) {
            throw new ClassPresentationGenerationException('El contenido no contiene exactamente la cantidad de diapositivas solicitada.', 'DECK_SLIDE_COUNT_INVALID', 502);
        }
        if ((int) data_get($deck, 'metadata.slide_count') !== $expectedSlides) {
            throw new ClassPresentationGenerationException('Los metadatos de la presentación no coinciden con la cantidad solicitada.', 'DECK_METADATA_INVALID', 502);
        }
        $configuration = $this->styleContract->ensure(
            (array) $presentation->configuration,
            (array) data_get($presentation->curricular_snapshot, 'course', []),
        );
        $applied = (array) data_get($deck, 'metadata.applied_configuration', []);
        $expected = [
            'visual_style' => (string) ($configuration['visual_style'] ?? ''),
            'palette' => (string) ($configuration['palette'] ?? ''),
            'methodologies' => $configuration['methodology'],
            'activities' => $configuration['activity'],
            'assessments' => $configuration['assessment'],
            'visual_resources' => $configuration['visual_resources'],
            'style_contract_version' => (string) data_get($configuration, 'style_contract.version'),
        ];
        foreach ($expected as $key => $value) {
            $actual = $applied[$key] ?? null;
            if (is_array($value)) {
                $expectedValues = array_values($value);
                $actualValues = is_array($actual) ? array_values($actual) : [];
                sort($expectedValues);
                sort($actualValues);
                if ($expectedValues !== $actualValues) {
                    throw new ClassPresentationGenerationException('La presentación no aplicó todas las alternativas seleccionadas.', 'DECK_CONFIGURATION_MISMATCH', 502);
                }
            } elseif ((string) $actual !== $value) {
                throw new ClassPresentationGenerationException('La presentación no respetó el estilo o la paleta solicitados.', 'DECK_CONFIGURATION_MISMATCH', 502);
            }
        }

        $allowedTypes = ['cover', 'opening', 'objectives', 'introduction', 'explanation', 'comparison', 'process', 'timeline', 'case', 'example', 'activity', 'assessment', 'synthesis', 'conclusion', 'bibliography', 'closure'];
        $totalMinutes = 0;
        foreach (array_values($slides) as $index => $slide) {
            if (! is_array($slide) || (int) ($slide['number'] ?? 0) !== $index + 1) {
                throw new ClassPresentationGenerationException('La numeración de las diapositivas es inválida.', 'DECK_NUMBERING_INVALID', 502);
            }
            foreach (['type', 'pedagogical_function', 'title', 'main_idea', 'visible_text', 'bullets', 'highlighted_concepts', 'estimated_minutes', 'sources'] as $required) {
                if (! array_key_exists($required, $slide)) {
                    throw new ClassPresentationGenerationException('La estructura de una diapositiva está incompleta.', 'DECK_REQUIRED_FIELD_MISSING', 502);
                }
            }
            if (! in_array($slide['type'], $allowedTypes, true) || trim((string) $slide['title']) === '') {
                throw new ClassPresentationGenerationException('La presentación contiene una diapositiva esencialmente inválida.', 'DECK_SLIDE_INVALID', 502);
            }
            if (! is_array($slide['bullets']) || count($slide['bullets']) > 6 || mb_strlen((string) $slide['title']) > 100 || mb_strlen((string) $slide['visible_text']) > 700) {
                throw new ClassPresentationGenerationException('La presentación excede los límites editoriales definidos.', 'DECK_TEXT_LIMIT_EXCEEDED', 502);
            }
            $totalMinutes += max(0, (int) $slide['estimated_minutes']);
        }
        if ($totalMinutes > (int) data_get($presentation->configuration, 'duration_minutes')) {
            throw new ClassPresentationGenerationException('La suma de tiempos sugeridos supera la duración de la clase.', 'DECK_DURATION_EXCEEDED', 502);
        }

        $objectiveCodes = collect(data_get($presentation->curricular_snapshot, 'objectives', []))->pluck('code')->filter();
        $serialized = mb_strtolower(json_encode([
            'metadata' => $deck['metadata'] ?? [],
            'slides' => $slides,
            'bibliography' => $deck['bibliography'] ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        foreach ($objectiveCodes as $code) {
            if (! str_contains($serialized, mb_strtolower((string) $code))) {
                throw new ClassPresentationGenerationException('La presentación no incluye todos los objetivos seleccionados.', 'DECK_OBJECTIVE_MISSING', 502);
            }
        }

        $this->validateTeacherGuide($presentation, $deck, array_values($slides), $totalMinutes, $expectedSlides);
    }

    /** @param array<string,mixed> $deck @param list<array<string,mixed>> $slides */
    private function validateTeacherGuide(ClassPresentation $presentation, array $deck, array $slides, int $slideMinutes, int $expectedSlides): void
    {
        $guide = $deck['teacher_guide'] ?? null;
        if (! is_array($guide) || ($guide['schema_version'] ?? null) !== 'v1.0') {
            throw new ClassPresentationGenerationException('La guía docente no tiene una estructura o versión válida.', 'TEACHER_GUIDE_INVALID', 502);
        }
        if (trim((string) ($guide['title'] ?? '')) === '') {
            throw new ClassPresentationGenerationException('La guía docente no incluye un título.', 'TEACHER_GUIDE_TITLE_MISSING', 502);
        }

        $configuredDuration = (int) data_get($presentation->configuration, 'duration_minutes');
        $script = $guide['slide_script'] ?? null;
        if (! is_array($script) || count($script) !== $expectedSlides) {
            throw new ClassPresentationGenerationException('La guía docente no cubre todas las diapositivas.', 'TEACHER_GUIDE_SLIDE_COUNT_INVALID', 502);
        }

        $scriptMinutes = 0;
        foreach (array_values($script) as $index => $entry) {
            $slide = $slides[$index] ?? [];
            if (! is_array($entry)
                || (int) ($entry['slide_number'] ?? 0) !== $index + 1
                || (int) ($entry['minutes'] ?? -1) !== (int) ($slide['estimated_minutes'] ?? -2)) {
                throw new ClassPresentationGenerationException('El guion docente no coincide con la numeración o los tiempos de la presentación.', 'TEACHER_GUIDE_SLIDE_MISMATCH', 502);
            }
            foreach (['purpose', 'teacher_script', 'transition'] as $required) {
                if (trim((string) ($entry[$required] ?? '')) === '') {
                    throw new ClassPresentationGenerationException('Una sección del guion docente está incompleta.', 'TEACHER_GUIDE_SCRIPT_INCOMPLETE', 502);
                }
            }
            foreach (['teacher_actions', 'questions', 'misconceptions', 'evidence_to_observe'] as $requiredArray) {
                if (! isset($entry[$requiredArray]) || ! is_array($entry[$requiredArray])) {
                    throw new ClassPresentationGenerationException('Una sección del guion docente está incompleta.', 'TEACHER_GUIDE_SCRIPT_INCOMPLETE', 502);
                }
            }
            $scriptMinutes += max(0, (int) $entry['minutes']);
        }
        if ($slideMinutes !== $configuredDuration || $scriptMinutes !== $configuredDuration) {
            throw new ClassPresentationGenerationException('La guía docente no cubre exactamente la duración de la clase.', 'TEACHER_GUIDE_DURATION_INVALID', 502);
        }

        $timeline = $guide['timeline'] ?? null;
        if (! is_array($timeline) || $timeline === []) {
            throw new ClassPresentationGenerationException('La guía docente no incluye una línea de tiempo válida.', 'TEACHER_GUIDE_TIMELINE_INVALID', 502);
        }
        $timelineMinutes = 0;
        $coveredSlides = [];
        foreach ($timeline as $phase) {
            if (! is_array($phase) || ! is_array($phase['slide_numbers'] ?? null)) {
                throw new ClassPresentationGenerationException('La guía docente no incluye una línea de tiempo válida.', 'TEACHER_GUIDE_TIMELINE_INVALID', 502);
            }
            $timelineMinutes += max(0, (int) ($phase['minutes'] ?? 0));
            foreach ($phase['slide_numbers'] as $slideNumber) {
                $coveredSlides[] = (int) $slideNumber;
            }
        }
        sort($coveredSlides);
        if ($timelineMinutes !== $configuredDuration || $coveredSlides !== range(1, $expectedSlides)) {
            throw new ClassPresentationGenerationException('La línea de tiempo de la guía no cubre exactamente la clase.', 'TEACHER_GUIDE_TIMELINE_INVALID', 502);
        }

        $expectedCodes = collect(data_get($presentation->curricular_snapshot, 'objectives', []))
            ->pluck('code')->map(fn (mixed $code): string => trim((string) $code))->filter()->sort()->values()->all();
        $actualCodes = collect(data_get($guide, 'at_a_glance.curricular_alignment', []))
            ->pluck('code')->map(fn (mixed $code): string => trim((string) $code))->filter()->sort()->values()->all();
        if ($expectedCodes !== $actualCodes) {
            throw new ClassPresentationGenerationException('La guía docente no está alineada exactamente con los objetivos seleccionados.', 'TEACHER_GUIDE_OBJECTIVES_MISMATCH', 502);
        }

        $activitySlides = collect($slides)->filter(fn (array $slide): bool => ($slide['type'] ?? null) === 'activity')->pluck('number')->map(fn (mixed $number): int => (int) $number)->sort()->values()->all();
        $assessmentSlides = collect($slides)->filter(fn (array $slide): bool => ($slide['type'] ?? null) === 'assessment')->pluck('number')->map(fn (mixed $number): int => (int) $number)->sort()->values()->all();
        $activitySupport = collect((array) ($guide['activity_support'] ?? []))->pluck('slide_number')->map(fn (mixed $number): int => (int) $number)->sort()->values()->all();
        $assessmentSupport = collect((array) ($guide['assessment_support'] ?? []))->pluck('slide_number')->map(fn (mixed $number): int => (int) $number)->sort()->values()->all();
        if ($activitySlides !== $activitySupport) {
            throw new ClassPresentationGenerationException('La guía docente no incluye apoyo para cada actividad.', 'TEACHER_GUIDE_ACTIVITY_MISMATCH', 502);
        }
        if ($assessmentSlides !== $assessmentSupport) {
            throw new ClassPresentationGenerationException('La guía docente no incluye apoyo para cada evaluación.', 'TEACHER_GUIDE_ASSESSMENT_MISMATCH', 502);
        }
    }
}
