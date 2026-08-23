<?php

namespace App\Services\LibroDigital\Curriculum;

use App\Contracts\LibroDigital\Curriculum\CurriculumDocumentParserInterface;

class GenericCurriculumDocumentParser implements CurriculumDocumentParserInterface
{
    public function __construct(protected readonly PdfTextNormalizer $normalizer) {}

    public function supports(string $documentType): bool
    {
        return true;
    }

    public function parse(array $classification, array $pages): array
    {
        $sections = $this->headingSections($pages);
        $candidates = [];
        foreach (['document_type', 'schedule_subject_id', 'education_level_id', 'grade_code', 'decree', 'edition', 'publication_year'] as $field) {
            if (($classification[$field] ?? null) === null) {
                continue;
            }
            $evidence = collect($classification['evidence'] ?? [])->first(fn (array $item): bool => match ($field) {
                'decree' => str_contains(mb_strtolower((string) ($item['match'] ?? '')), 'decreto'),
                'edition', 'publication_year' => str_contains(mb_strtolower((string) ($item['match'] ?? '')), 'edici'),
                default => false,
            });
            $candidates[] = [
                'entity_type' => 'identification',
                'candidate_key' => 'identification:'.$field,
                'detected_value' => is_scalar($classification[$field]) ? (string) $classification[$field] : null,
                'normalized_value' => is_scalar($classification[$field]) ? $this->normalizer->key((string) $classification[$field]) : null,
                'structured_payload' => ['field' => $field, 'value' => $classification[$field]],
                'confidence' => (float) data_get($classification, 'confidence.'.match ($field) {
                    'schedule_subject_id' => 'subject',
                    'education_level_id', 'grade_code' => 'grade',
                    default => $field,
                }, 0.80),
                'physical_page' => $evidence['page'] ?? 1,
                'printed_page' => $evidence['printed_page'] ?? null,
                'source_excerpt' => $evidence['excerpt'] ?? data_get($pages, '0.normalized_text'),
                'suggested_action' => 'review',
                'warnings' => [],
            ];
        }

        return [
            'sections' => $sections,
            'candidates' => $candidates,
            'warnings' => ['generic_parser_used'],
            'summary' => ['section_count' => count($sections), 'candidate_count' => count($candidates)],
        ];
    }

    /** @param list<array<string,mixed>> $pages @return list<array<string,mixed>> */
    protected function headingSections(array $pages): array
    {
        $recognized = [
            'presentación' => 'presentation',
            'nociones básicas' => 'basic_notions',
            'orientaciones para implementar el programa' => 'implementation_guidance',
            'orientaciones para planificar el aprendizaje' => 'planning_guidance',
            'orientaciones para evaluar los aprendizajes' => 'assessment_guidance',
            'organización curricular' => 'curriculum_organization',
            'orientaciones didácticas' => 'didactic_guidance',
            'visión global del año' => 'annual_overview',
            'bibliografía' => 'bibliography',
            'anexos' => 'annexes',
        ];
        $starts = [];
        foreach ($pages as $page) {
            $key = $this->normalizer->key((string) ($page['normalized_text'] ?? ''));
            foreach ($recognized as $heading => $type) {
                if (! isset($starts[$type]) && str_contains($key, $this->normalizer->key($heading))) {
                    $starts[$type] = [
                        'section_type' => $type,
                        'heading' => $heading,
                        'normalized_heading' => $this->normalizer->key($heading),
                        'page_start' => (int) $page['physical_page_number'],
                        'confidence' => 0.90,
                    ];
                }
            }
        }
        $ordered = collect($starts)->sortBy('page_start')->values();

        return $ordered->map(function (array $section, int $index) use ($ordered, $pages): array {
            $next = $ordered->get($index + 1);
            $end = $next ? max($section['page_start'], (int) $next['page_start'] - 1) : (int) data_get($pages, array_key_last($pages).'.physical_page_number', $section['page_start']);
            $texts = collect($pages)->filter(fn (array $page): bool => (int) $page['physical_page_number'] >= $section['page_start'] && (int) $page['physical_page_number'] <= $end)->pluck('normalized_text')->implode("\n\n");

            return [
                ...$section,
                'official_order' => $index + 1,
                'page_end' => $end,
                'full_text' => $texts,
                'structured_data' => null,
                'review_status' => 'pending',
            ];
        })->all();
    }
}
