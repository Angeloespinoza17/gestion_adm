<?php

namespace App\Services\LibroDigital\Curriculum;

use App\Contracts\LibroDigital\Curriculum\CurriculumDocumentClassifierInterface;
use App\Models\EducationLevel;
use App\Models\Schedule\ScheduleSubject;
use App\Services\LibroDigital\CurriculumGradeResolver;
use Illuminate\Support\Collection;

class HeuristicCurriculumDocumentClassifier implements CurriculumDocumentClassifierInterface
{
    public function __construct(
        private readonly PdfTextNormalizer $normalizer,
        private readonly CurriculumGradeResolver $grades,
    ) {}

    public function classify(array $pages, array $hints = []): array
    {
        $front = collect($pages)->take(24);
        $text = $front->pluck('normalized_text')->implode("\n");
        $key = $this->normalizer->key($text);
        $documentType = $this->documentType($key);
        $gradeCode = $this->gradeCode($text);
        $subject = $this->subject($pages, isset($hints['schedule_subject_id']) ? (int) $hints['schedule_subject_id'] : null);
        $educationLevel = $this->educationLevel($gradeCode, isset($hints['education_level_id']) ? (int) $hints['education_level_id'] : null);
        $evidence = fn (string $pattern): ?array => $this->evidence($front, $pattern);
        $decreeEvidence = $evidence('/Decreto\s+(?:Supremo\s+de\s+Educaci[oó]n\s+)?N?[º°o]?\s*[^\n]{0,80}/iu');
        $editionEvidence = $evidence('/(?:Primera|Segunda|Tercera|Cuarta|Quinta)\s+Edici[oó]n\s*:?\s*\d{4}/iu');
        $titleEvidence = $evidence('/[^\n]{2,100}Programa de Estudio[^\n]{0,100}/iu');
        $decree = $decreeEvidence ? trim((string) $decreeEvidence['match']) : null;
        $edition = $editionEvidence ? trim((string) $editionEvidence['match']) : null;
        preg_match('/(19|20)\d{2}/', (string) $edition, $yearMatch);

        $warnings = [];
        if (! $subject) {
            $warnings[] = 'subject_not_detected';
        }
        if (! $educationLevel || ! $gradeCode) {
            $warnings[] = 'grade_not_detected';
        }
        if ($documentType === 'unknown') {
            $warnings[] = 'document_type_unknown';
        }

        $coverTitle = collect($pages)
            ->take(8)
            ->pluck('normalized_text')
            ->first(fn (?string $page): bool => mb_strlen(trim((string) $page)) >= 30);

        return [
            'document_type' => $documentType,
            'title' => collect(explode("\n", (string) ($coverTitle ?: $titleEvidence['match'] ?? 'Documento curricular')))->take(3)->implode(' - '),
            'schedule_subject_id' => $subject?->id,
            'subject_code' => $subject?->code,
            'subject_name' => $subject?->resolvedDisplayName(),
            'education_level_id' => $educationLevel?->id,
            'education_level_name' => $educationLevel?->name,
            'grade_code' => $gradeCode,
            'level_code' => $gradeCode ? $this->grades->levelForGrade($gradeCode) : null,
            'curriculum_track' => in_array($gradeCode, ['3M', '4M'], true) ? null : 'GENERAL',
            'issuing_authority' => str_contains($key, 'ministerio de educacion') ? 'Ministerio de Educación' : null,
            'decree' => $decree,
            'edition' => $edition,
            'publication_year' => isset($yearMatch[0]) ? (int) $yearMatch[0] : null,
            'confidence' => [
                'document_type' => $documentType === 'unknown' ? 0.30 : 0.98,
                'subject' => $subject ? 0.96 : 0.20,
                'grade' => $educationLevel && $gradeCode ? 0.97 : 0.20,
                'decree' => $decree ? 0.96 : 0.20,
                'edition' => $edition ? 0.96 : 0.20,
            ],
            'evidence' => array_values(array_filter([
                'title' => $titleEvidence,
                'decree' => $decreeEvidence,
                'edition' => $editionEvidence,
            ])),
            'warnings' => $warnings,
        ];
    }

    private function documentType(string $key): string
    {
        return match (true) {
            str_contains($key, 'programa de estudio') => 'program_study',
            str_contains($key, 'bases curriculares') => 'curricular_bases',
            str_contains($key, 'plan de estudio') => 'study_plan',
            str_contains($key, 'priorizacion curricular') => 'curriculum_prioritization',
            str_contains($key, 'orientacion curricular') => 'curriculum_guidance',
            default => 'unknown',
        };
    }

    private function gradeCode(string $text): ?string
    {
        $key = $this->normalizer->key($text);
        $wordOrdinals = [
            'primer' => 1, 'primero' => 1, 'segundo' => 2, 'tercero' => 3, 'cuarto' => 4,
            'quinto' => 5, 'sexto' => 6, 'septimo' => 7, 'octavo' => 8,
        ];
        foreach ($wordOrdinals as $word => $number) {
            if (preg_match('/\b'.$word.'(?:\s+ano)?\s+basico\b/u', $key) === 1) {
                return $number.'B';
            }
        }
        if (preg_match('/\b([1-8])\s*(?:o|ro)?\s+basico\b/u', $key, $match) === 1) {
            return $match[1].'B';
        }
        if (preg_match('/\b([1-4])\s*(?:o|ro)?\s+medio\b/u', $key, $match) === 1) {
            return $match[1].'M';
        }
        if (preg_match('/\b(nt\s*[12]|nivel\s+de\s+transicion\s*[12])\b/u', $key, $match) === 1) {
            return str_contains($match[1], '2') ? 'NT2' : 'NT1';
        }

        return null;
    }

    /** @param list<array<string,mixed>> $pages */
    private function subject(array $pages, ?int $hint): ?ScheduleSubject
    {
        if ($hint) {
            return ScheduleSubject::query()->with('catalogProfile')->whereKey($hint)->where('active', true)->first();
        }
        $frontPages = collect($pages)->take(24)->values();

        return ScheduleSubject::query()->with(['catalogProfile', 'externalAliases'])
            ->where('active', true)
            ->get()
            ->map(function (ScheduleSubject $subject) use ($frontPages): array {
                $names = collect([
                    $subject->name,
                    $subject->resolvedDisplayName(),
                    ...$subject->externalAliases->pluck('external_name')->all(),
                ])->map(fn (string $name): string => $this->normalizer->key($name))->filter()->unique();
                $score = $frontPages->map(function (array $page, int $index) use ($names): int {
                    $key = $this->normalizer->key((string) ($page['normalized_text'] ?? ''));
                    $nameScore = $names->max(fn (string $name): int => mb_strlen($name) >= 4 && str_contains($key, $name) ? mb_strlen($name) : 0) ?? 0;
                    if ($nameScore === 0) {
                        return 0;
                    }

                    // Portada, portadilla y ficha legal son evidencia de identificación;
                    // las menciones posteriores pueden ser ejemplos interdisciplinarios.
                    $pageWeight = match (true) {
                        $index === 0 => 10000,
                        $index <= 7 => 5000 - ($index * 100),
                        default => max(0, 500 - ($index * 10)),
                    };

                    return $pageWeight + $nameScore;
                })->max() ?? 0;

                return ['subject' => $subject, 'score' => $score];
            })
            ->sortByDesc('score')
            ->first(fn (array $candidate): bool => $candidate['score'] >= 4)['subject'] ?? null;
    }

    private function educationLevel(?string $gradeCode, ?int $hint): ?EducationLevel
    {
        if ($hint) {
            return EducationLevel::query()->find($hint);
        }
        if (! $gradeCode) {
            return null;
        }

        return EducationLevel::query()->orderBy('order')->get()
            ->first(fn (EducationLevel $level): bool => $this->grades->fromEducationLevel($level) === $gradeCode);
    }

    /** @param Collection<int,array<string,mixed>> $pages */
    private function evidence(Collection $pages, string $pattern): ?array
    {
        foreach ($pages as $page) {
            $text = (string) ($page['normalized_text'] ?? '');
            if (preg_match($pattern, $text, $match) === 1) {
                return [
                    'page' => (int) $page['physical_page_number'],
                    'printed_page' => $page['printed_page_label'] ?? null,
                    'match' => trim((string) $match[0]),
                    'excerpt' => mb_substr($text, max(0, mb_strpos($text, $match[0]) - 120), 520),
                ];
            }
        }

        return null;
    }
}
